<?php

namespace App\Http\Controllers;

use App\Models\Registry\Startup;
use App\Models\Talent\Interview;
use App\Models\Talent\JobApplication;
use App\Models\Talent\TalentProfile;
use App\Models\Talent\Vacancy;
use App\Models\Talent\VacancyInvitation;
use App\Registry\RegistryException;
use App\Support\Audit;
use App\Talent\TalentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** A candidate's own profile, CV, applications and interviews. */
class CandidateController extends Controller
{
    public const CV_MIME_TYPES = ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];

    public function __construct(private readonly TalentService $talent) {}

    public function profile(Request $request): View
    {
        return view('talent.profile', ['profile' => TalentProfile::query()->where('user_id', $request->user()->id)->first()]);
    }

    public function saveProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'headline' => ['required', 'string', 'max:150'],
            'summary' => ['nullable', 'string', 'max:3000'],
            'skills' => ['nullable', 'string', 'max:1000'],
            'work_types' => ['nullable', 'array'],
            'work_types.*' => [Rule::in(array_keys(Vacancy::TYPES))],
            'location' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', Rule::in(array_keys(Startup::COUNTRIES))],
            'visibility' => ['required', Rule::in(array_keys(TalentProfile::VISIBILITY))],
            'cv' => ['nullable', 'file', 'max:'.config('nsp.talent.cv_max_kb'), 'extensions:pdf,docx', 'mimetypes:'.implode(',', self::CV_MIME_TYPES)],
            'remove_cv' => ['nullable', 'boolean'],
        ]);
        $profile = TalentProfile::query()->where('user_id', $request->user()->id)->first() ?? new TalentProfile;
        $profile->user_id = $request->user()->id;
        $profile->fill([
            'headline' => $data['headline'], 'summary' => $data['summary'] ?? null, 'skills' => TalentProfile::normaliseSkills($data['skills'] ?? ''),
            'work_types' => $data['work_types'] ?? [], 'location' => $data['location'] ?? null, 'country' => $data['country'] ?? null,
            'open_to_work' => $request->boolean('open_to_work'), 'visibility' => $data['visibility'],
        ]);
        // Opting in to search is recorded with the wording version; opting out clears it.
        if ($profile->visibility === 'startups') {
            if (! $profile->consented_at || $profile->isDirty('visibility')) {
                $profile->consented_at = now();
                $profile->consent_version = config('nsp.talent.consent_version');
            }
        } else {
            $profile->consented_at = null;
            $profile->consent_version = null;
        }
        if ($request->boolean('remove_cv') && $profile->cv_path) {
            Storage::disk('local')->delete($profile->cv_path);
            $profile->cv_path = null;
            $profile->cv_name = null;
        }
        if ($file = $request->file('cv')) {
            if ($profile->cv_path) {
                Storage::disk('local')->delete($profile->cv_path);
            }
            $profile->cv_path = $file->store('talent/cv/'.$request->user()->id, 'local');
            $profile->cv_name = mb_substr(basename($file->getClientOriginalName()), 0, 200);
        }
        $isNew = ! $profile->exists;
        $profile->save();
        Audit::log($isNew ? 'talent.profile_created' : 'talent.profile_updated', $profile, ['visibility' => $profile->visibility]);

        return redirect()->route('talent.profile')->with('status', 'Candidate profile saved.');
    }

    public function deleteProfile(Request $request): RedirectResponse
    {
        $profile = TalentProfile::query()->where('user_id', $request->user()->id)->firstOrFail();
        $profile->delete();
        Audit::log('talent.profile_deleted', null, ['user_id' => $request->user()->id]);

        return redirect()->route('talent.profile')->with('status', 'Your candidate profile and CV were deleted. Applications you already sent stay with those startups until their retention period ends; withdraw them to delete their CV copies now.');
    }

    public function dashboard(Request $request): View
    {
        $userId = $request->user()->id;

        return view('talent.dashboard', [
            'profile' => TalentProfile::query()->where('user_id', $userId)->first(),
            'applications' => JobApplication::query()->with('vacancy', 'startup', 'interviews', 'events')->where('applicant_id', $userId)->latest()->get(),
            'invitations' => VacancyInvitation::query()->with('vacancy.startup')->where('user_id', $userId)
                ->whereDoesntHave('vacancy.applications', fn ($q) => $q->where('applicant_id', $userId))->latest()->get()
                ->filter(fn ($i) => $i->vacancy?->isAcceptingApplications()),
        ]);
    }

    public function cv(Request $request): StreamedResponse
    {
        $profile = TalentProfile::query()->where('user_id', $request->user()->id)->first();
        abort_unless($profile?->cv_path && Storage::disk('local')->exists($profile->cv_path), 404);

        return Storage::disk('local')->download($profile->cv_path, $profile->cv_name ?: 'cv', ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    public function withdraw(Request $request, JobApplication $application): RedirectResponse
    {
        abort_unless($application->applicant_id === $request->user()->id, 404);
        try {
            $this->talent->withdraw($application, $request->user());
        } catch (RegistryException $exception) {
            return back()->withErrors(['application' => $exception->getMessage()]);
        }

        return back()->with('status', 'Application withdrawn and its CV copy deleted.');
    }

    public function respondToInterview(Request $request, Interview $interview): RedirectResponse
    {
        abort_unless($interview->application?->applicant_id === $request->user()->id, 404);
        $data = $request->validate(['decision' => ['required', Rule::in(['confirm', 'decline'])], 'candidate_note' => ['nullable', 'string', 'max:500']]);
        try {
            $this->talent->respondToInterview($interview, $request->user(), $data['decision'] === 'confirm', $data['candidate_note'] ?? null);
        } catch (RegistryException $exception) {
            return back()->withErrors(['interview' => $exception->getMessage()]);
        }

        return back()->with('status', $data['decision'] === 'confirm' ? 'Interview confirmed.' : 'The team has been asked for another time.');
    }
}
