<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Registry\Startup;
use App\Models\Talent\Interview;
use App\Models\Talent\JobApplication;
use App\Models\Talent\TalentProfile;
use App\Models\Talent\Vacancy;
use App\Models\Talent\VacancyInvitation;
use App\Models\User;
use App\Registry\RegistryException;
use App\Support\Audit;
use App\Support\Csv;
use App\Talent\TalentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** A startup's vacancies, applications pipeline, interviews and candidate search. Team only. */
class TalentController extends Controller
{
    use ResolvesMembership;

    public function __construct(private readonly TalentService $talent) {}

    public function index(Request $request, Startup $startup): View
    {
        $membership = $this->membershipOr404($request, $startup);
        $vacancies = Vacancy::query()->where('startup_uuid', $startup->uuid)
            ->withCount(['applications', 'applications as new_count' => fn ($q) => $q->where('status', 'submitted')])->latest()->get();

        return view('workspace.talent.index', ['startup' => $startup, 'membership' => $membership, 'vacancies' => $vacancies, 'vacancy' => new Vacancy]);
    }

    public function store(Request $request, Startup $startup): RedirectResponse
    {
        $this->editorOr403($request, $startup);
        $vacancy = Vacancy::query()->create($this->validated($request) + ['startup_uuid' => $startup->uuid, 'created_by' => $request->user()->id]);
        Audit::log('talent.vacancy_created', $vacancy);

        return redirect()->route('workspace.talent.edit', [$startup, $vacancy])->with('status', 'Draft saved. Publish it when you are ready.');
    }

    public function edit(Request $request, Startup $startup, Vacancy $vacancy): View
    {
        $membership = $this->editorOr403($request, $startup);
        $this->ownedOr404($startup, $vacancy);

        return view('workspace.talent.edit', ['startup' => $startup, 'membership' => $membership, 'vacancy' => $vacancy]);
    }

    public function update(Request $request, Startup $startup, Vacancy $vacancy): RedirectResponse
    {
        $this->editorOr403($request, $startup);
        $this->ownedOr404($startup, $vacancy);
        abort_if($vacancy->status === 'removed', 403, 'Hive took this vacancy down.');
        $vacancy->update($this->validated($request));
        Audit::log('talent.vacancy_updated', $vacancy);

        return back()->with('status', 'Vacancy saved.');
    }

    public function setStatus(Request $request, Startup $startup, Vacancy $vacancy): RedirectResponse
    {
        $this->editorOr403($request, $startup);
        $this->ownedOr404($startup, $vacancy);
        abort_if($vacancy->status === 'removed', 403, 'Hive took this vacancy down.');
        $status = $request->validate(['status' => ['required', Rule::in(['draft', 'open', 'closed', 'filled'])]])['status'];
        if ($status === 'open' && $vacancy->closes_on && $vacancy->closes_on->isPast() && ! $vacancy->closes_on->isToday()) {
            return back()->withErrors(['status' => 'Set a closing date in the future before publishing.']);
        }
        $vacancy->forceFill([
            'status' => $status,
            'published_at' => $status === 'open' ? ($vacancy->published_at ?? now()) : $vacancy->published_at,
            'closed_at' => in_array($status, ['closed', 'filled'], true) ? now() : null,
        ])->save();
        Audit::log('talent.vacancy_'.$status, $vacancy);

        return back()->with('status', ['draft' => 'Moved back to draft.', 'open' => 'Published on the jobs board.', 'closed' => 'Closed. No new applications.', 'filled' => 'Marked as filled.'][$status]);
    }

    public function pipeline(Request $request, Startup $startup, Vacancy $vacancy): View
    {
        $membership = $this->editorOr403($request, $startup);
        $this->ownedOr404($startup, $vacancy);
        $status = $request->query('status');
        $applications = $vacancy->applications()->with('applicant')
            ->when(array_key_exists((string) $status, JobApplication::STATUSES), fn ($q) => $q->where('status', $status))
            ->orderByRaw("case status when 'submitted' then 0 when 'shortlisted' then 1 when 'interview' then 2 when 'offer' then 3 when 'hired' then 4 else 5 end")
            ->latest()->get();
        $profiles = TalentProfile::query()->whereIn('user_id', $applications->pluck('applicant_id'))->get()->keyBy('user_id');

        return view('workspace.talent.pipeline', [
            'startup' => $startup, 'membership' => $membership, 'vacancy' => $vacancy, 'applications' => $applications, 'profiles' => $profiles,
            'counts' => $vacancy->applications()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'), 'status' => $status,
        ]);
    }

    /** Applications for one vacancy: what the team already sees on screen, without CVs or team notes. */
    public function exportApplications(Request $request, Startup $startup, Vacancy $vacancy): StreamedResponse
    {
        $this->editorOr403($request, $startup);
        $this->ownedOr404($startup, $vacancy);
        Audit::log('talent.applications_exported', $vacancy);
        $rows = $vacancy->applications()->with('applicant')->orderBy('created_at')->get()->map(fn (JobApplication $a) => [
            $a->created_at->format('Y-m-d H:i'), $a->applicant?->name, $a->applicant?->email,
            TalentProfile::query()->where('user_id', $a->applicant_id)->value('headline'), JobApplication::STATUSES[$a->status], $a->invited ? 'yes' : 'no',
        ]);

        return Csv::download('applications-'.$vacancy->slug.'.csv', ['Applied', 'Name', 'Email', 'Headline', 'Stage', 'Invited'], $rows);
    }

    public function application(Request $request, Startup $startup, JobApplication $application): View
    {
        $membership = $this->editorOr403($request, $startup);
        abort_unless($application->startup_uuid === $startup->uuid, 404);
        $application->load('vacancy', 'applicant', 'interviews', 'events.actor');

        return view('workspace.talent.application', [
            'startup' => $startup, 'membership' => $membership, 'application' => $application,
            'profile' => TalentProfile::query()->where('user_id', $application->applicant_id)->first(),
        ]);
    }

    public function move(Request $request, Startup $startup, JobApplication $application): RedirectResponse
    {
        $this->membershipOr404($request, $startup);
        abort_unless($application->startup_uuid === $startup->uuid, 404);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(JobApplication::STATUSES))],
            'message' => ['nullable', 'string', 'max:500'],
            'team_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        return $this->attempt(fn () => $this->talent->move($application, $request->user(), $data['status'], $data['message'] ?? null, $data['team_notes'] ?? null), 'Application updated.');
    }

    public function scheduleInterview(Request $request, Startup $startup, JobApplication $application): RedirectResponse
    {
        $this->membershipOr404($request, $startup);
        abort_unless($application->startup_uuid === $startup->uuid, 404);
        $data = $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now', 'before:'.now()->addMonths(6)->toDateString()],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:240'],
            'mode' => ['required', Rule::in(array_keys(Interview::MODES))],
            'location' => ['nullable', 'string', 'max:255', Rule::when($request->input('mode') === 'online', ['url:https'])],
        ]);

        return $this->attempt(fn () => $this->talent->scheduleInterview($application, $request->user(), $data), 'Interview proposed. The candidate has been asked to confirm.');
    }

    public function updateInterview(Request $request, Startup $startup, Interview $interview): RedirectResponse
    {
        $this->membershipOr404($request, $startup);
        abort_unless($interview->application?->startup_uuid === $startup->uuid, 404);
        $data = $request->validate([
            'status' => ['required', Rule::in(['done', 'cancelled', $interview->status])],
            'team_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        return $this->attempt(fn () => $this->talent->updateInterview($interview, $request->user(), $data), 'Interview saved.');
    }

    public function cv(Request $request, Startup $startup, JobApplication $application): StreamedResponse
    {
        $this->editorOr403($request, $startup);
        abort_unless($application->startup_uuid === $startup->uuid && $this->talent->cvAccess($request->user(), $application) === 'team', 404);
        abort_unless(Storage::disk('local')->exists($application->cv_path), 404);
        Audit::log('talent.cv_viewed', $application);

        return Storage::disk('local')->download($application->cv_path, $application->cv_name ?: 'cv', ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    public function search(Request $request, Startup $startup): View
    {
        $membership = $this->editorOr403($request, $startup);
        $q = trim((string) $request->query('q', ''));
        $type = $request->query('type');
        $candidates = TalentProfile::query()->searchable()->with('user:id,name')
            ->where('user_id', '!=', $request->user()->id)
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('headline', 'like', '%'.$q.'%')->orWhere('skills', 'like', '%'.$q.'%')->orWhere('location', 'like', '%'.$q.'%')))
            ->when(array_key_exists((string) $type, Vacancy::TYPES), fn ($query) => $query->where('work_types', 'like', '%"'.$type.'"%'))
            ->latest('updated_at')->paginate(20)->withQueryString();

        return view('workspace.talent.search', [
            'startup' => $startup, 'membership' => $membership, 'candidates' => $candidates, 'q' => $q, 'type' => $type,
            'openVacancies' => Vacancy::query()->acceptingApplications()->where('startup_uuid', $startup->uuid)->pluck('title', 'uuid')->all(),
            'invited' => VacancyInvitation::query()->whereIn('vacancy_uuid', Vacancy::query()->where('startup_uuid', $startup->uuid)->pluck('uuid'))
                ->get()->groupBy('user_id'),
        ]);
    }

    public function invite(Request $request, Startup $startup): RedirectResponse
    {
        $this->editorOr403($request, $startup);
        $data = $request->validate(['vacancy_uuid' => ['required', 'string'], 'user_id' => ['required', 'integer'], 'message' => ['nullable', 'string', 'max:1000']]);
        $vacancy = Vacancy::query()->where('startup_uuid', $startup->uuid)->findOrFail($data['vacancy_uuid']);
        $candidate = User::query()->findOrFail($data['user_id']);

        return $this->attempt(fn () => $this->talent->invite($vacancy, $request->user(), $candidate, $data['message'] ?? null), 'Invitation sent. Applying is up to them.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(array_keys(Vacancy::TYPES))],
            'description' => ['required', 'string', 'max:8000'],
            'requirements' => ['nullable', 'string', 'max:4000'],
            'skills' => ['nullable', 'string', 'max:1000'],
            'location' => ['nullable', 'string', 'max:120'],
            'pay_note' => ['nullable', 'string', 'max:150'],
            'closes_on' => ['nullable', 'date', 'after_or_equal:today'],
        ]);
        $data['skills'] = TalentProfile::normaliseSkills($data['skills'] ?? '');
        $data['remote'] = $request->boolean('remote');

        return $data;
    }

    private function editorOr403(Request $request, Startup $startup)
    {
        $membership = $this->membershipOr404($request, $startup);
        abort_unless($membership->canEdit(), 403, 'Only owners, founders and admins can manage hiring.');

        return $membership;
    }

    private function ownedOr404(Startup $startup, Vacancy $vacancy): void
    {
        abort_unless($vacancy->startup_uuid === $startup->uuid, 404);
    }

    private function attempt(callable $action, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (RegistryException $exception) {
            return back()->withErrors(['talent' => $exception->getMessage()])->withInput();
        }

        return back()->with('status', $message);
    }
}
