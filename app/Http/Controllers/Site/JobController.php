<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Registry\StartupProfile;
use App\Models\Talent\JobApplication;
use App\Models\Talent\TalentProfile;
use App\Models\Talent\Vacancy;
use App\Models\Talent\VacancyInvitation;
use App\Registry\RegistryException;
use App\Talent\TalentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Public jobs board: open vacancies from active startups. */
class JobController extends Controller
{
    public function __construct(private readonly TalentService $talent) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(array_keys(Vacancy::TYPES))],
            'sector' => ['nullable', Rule::in(StartupProfile::SECTORS)],
            'remote' => ['nullable', 'boolean'],
        ]);
        $vacancies = Vacancy::query()->acceptingApplications()->with('startup.profile')
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w->where('title', 'like', '%'.$term.'%')
                ->orWhere('description', 'like', '%'.$term.'%')->orWhere('skills', 'like', '%'.$term.'%')))
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($filters['sector'] ?? null, fn ($q, $sector) => $q->whereHas('startup.profile', fn ($p) => $p->where('sector', $sector)))
            ->when($request->boolean('remote'), fn ($q) => $q->where('remote', true))
            ->latest('published_at')->paginate(20)->withQueryString();

        return view('jobs.index', ['vacancies' => $vacancies, 'filters' => $filters]);
    }

    public function show(Vacancy $vacancy): View
    {
        $user = Auth::user();
        abort_unless($vacancy->isAcceptingApplications() || ($user && JobApplication::query()->where('vacancy_uuid', $vacancy->uuid)->where('applicant_id', $user->id)->exists()), 404);

        return view('jobs.show', [
            'vacancy' => $vacancy->load('startup.profile'),
            'application' => $user ? JobApplication::query()->where('vacancy_uuid', $vacancy->uuid)->where('applicant_id', $user->id)->first() : null,
            'profile' => $user ? TalentProfile::query()->where('user_id', $user->id)->first() : null,
            'invitation' => $user ? VacancyInvitation::query()->where('vacancy_uuid', $vacancy->uuid)->where('user_id', $user->id)->first() : null,
        ]);
    }

    public function apply(Request $request, Vacancy $vacancy): RedirectResponse
    {
        $data = $request->validate(['cover_note' => ['nullable', 'string', 'max:3000'], 'consent' => ['accepted']]);
        try {
            $this->talent->apply($vacancy, $request->user(), $data['cover_note'] ?? null);
        } catch (RegistryException $exception) {
            return back()->withErrors(['apply' => $exception->getMessage()])->withInput();
        }

        return redirect()->route('talent.dashboard')->with('status', 'Application sent to '.$vacancy->startup->display_name.'. We will email you when it changes.');
    }
}
