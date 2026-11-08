<?php

namespace App\Talent;

use App\Communication\Notifier;
use App\Models\Registry\Startup;
use App\Models\Registry\StartupMembership;
use App\Models\Talent\Interview;
use App\Models\Talent\JobApplication;
use App\Models\Talent\PipelineEvent;
use App\Models\Talent\TalentProfile;
use App\Models\Talent\Vacancy;
use App\Models\Talent\VacancyInvitation;
use App\Models\User;
use App\Registry\RegistryException;
use App\Support\Audit;
use Illuminate\Support\Facades\Storage;

/**
 * Rules for talent and hiring:
 * - a candidate's profile is hidden unless they opt in; search never shows the CV or email;
 * - applying shares the cover note, profile and a copy of the CV with that startup's team only;
 * - team notes and interview notes are never shown to the candidate;
 * - only owners, founders and admins of the startup see applications and CVs and change them;
 * - application CVs are deleted after the retention period (purgeExpiredCvs).
 */
class TalentService
{
    public function apply(Vacancy $vacancy, User $candidate, ?string $coverNote): JobApplication
    {
        if (! $vacancy->isAcceptingApplications()) {
            throw new RegistryException('This vacancy is not taking applications.');
        }
        if ($vacancy->startup->membershipOf($candidate)) {
            throw new RegistryException('You are on this startup’s team.');
        }
        $profile = TalentProfile::query()->where('user_id', $candidate->id)->first();
        if (! $profile) {
            throw new RegistryException('Create your candidate profile first.');
        }
        if (JobApplication::query()->where('vacancy_uuid', $vacancy->uuid)->where('applicant_id', $candidate->id)->exists()) {
            throw new RegistryException('You already applied for this vacancy.');
        }

        $application = JobApplication::query()->create([
            'vacancy_uuid' => $vacancy->uuid, 'startup_uuid' => $vacancy->startup_uuid, 'applicant_id' => $candidate->id,
            'cover_note' => $coverNote ? mb_substr(trim($coverNote), 0, 3000) : null, 'status' => 'submitted', 'status_changed_at' => now(),
            'invited' => VacancyInvitation::query()->where('vacancy_uuid', $vacancy->uuid)->where('user_id', $candidate->id)->exists(),
        ]);
        // The application keeps its own copy, so later CV changes or profile deletion do not alter what was sent.
        if ($profile->cv_path && Storage::disk('local')->exists($profile->cv_path)) {
            $copy = 'talent/applications/'.$application->uuid.'.'.pathinfo($profile->cv_path, PATHINFO_EXTENSION);
            Storage::disk('local')->copy($profile->cv_path, $copy);
            $application->forceFill(['cv_path' => $copy, 'cv_name' => $profile->cv_name])->save();
        }
        PipelineEvent::query()->create(['application_uuid' => $application->uuid, 'to_status' => 'submitted', 'actor_id' => $candidate->id]);
        Audit::log('talent.applied', $application);

        $this->notifyTeam($vacancy->startup, 'New application: '.$vacancy->title, [
            'Someone applied for “'.$vacancy->title.'”. Open NSP to review it.',
        ], route('workspace.talent.vacancy', [$vacancy->startup_uuid, $vacancy]));

        return $application;
    }

    public function move(JobApplication $application, User $actor, string $status, ?string $message, ?string $teamNotes): void
    {
        $this->requireEditor($application->startup, $actor);
        if ($application->status === 'withdrawn') {
            throw new RegistryException('The candidate withdrew this application.');
        }
        if (! array_key_exists($status, JobApplication::STATUSES) || $status === 'withdrawn') {
            throw new RegistryException('Choose a valid stage.');
        }
        $from = $application->status;
        $application->forceFill(['team_notes' => $teamNotes ?? $application->team_notes])->save();
        if ($from === $status) {
            return;
        }
        $application->forceFill(['status' => $status, 'status_changed_at' => now()])->save();
        PipelineEvent::query()->create([
            'application_uuid' => $application->uuid, 'from_status' => $from, 'to_status' => $status, 'actor_id' => $actor->id,
            'message' => $message ? mb_substr(trim($message), 0, 500) : null,
        ]);
        Audit::log('talent.status_changed', $application, ['from' => $from, 'to' => $status]);
        $this->notify($application->applicant, 'Update on your application: '.$application->vacancy->title, array_filter([
            $application->startup->display_name.': '.JobApplication::CANDIDATE_LABELS[$status].'.',
            $message ? 'Message from the team: '.$message : null,
        ]), route('talent.dashboard'));
    }

    public function scheduleInterview(JobApplication $application, User $actor, array $data): Interview
    {
        $this->requireEditor($application->startup, $actor);
        if ($application->isClosed()) {
            throw new RegistryException('This application is closed.');
        }
        $interview = Interview::query()->create($data + ['application_uuid' => $application->uuid, 'status' => 'proposed', 'created_by' => $actor->id]);
        if (in_array($application->status, ['submitted', 'shortlisted'], true)) {
            $this->move($application, $actor, 'interview', null, null);
        }
        Audit::log('talent.interview_proposed', $application);
        $this->notify($application->applicant, 'Interview proposed: '.$application->vacancy->title, [
            $application->startup->display_name.' proposed an interview on '.$interview->scheduled_at->format('j F Y, H:i').' ('.Interview::MODES[$interview->mode].').',
            'Please confirm it or ask for another time on NSP.',
        ], route('talent.dashboard'));

        return $interview;
    }

    public function respondToInterview(Interview $interview, User $candidate, bool $confirm, ?string $note): void
    {
        $application = $interview->application;
        if ($application->applicant_id !== $candidate->id) {
            throw new RegistryException('This is not your interview.');
        }
        if ($interview->status !== 'proposed') {
            throw new RegistryException('This interview was already answered.');
        }
        $interview->update(['status' => $confirm ? 'confirmed' : 'declined', 'candidate_note' => $note ? mb_substr(trim($note), 0, 500) : null]);
        Audit::log($confirm ? 'talent.interview_confirmed' : 'talent.interview_declined', $application);
        $this->notifyTeam($application->startup, ($confirm ? 'Interview confirmed: ' : 'Interview needs a new time: ').$application->vacancy->title, [
            $confirm ? 'The candidate confirmed the interview on '.$interview->scheduled_at->format('j F Y, H:i').'.' : 'The candidate asked for another time.',
        ], route('workspace.talent.application', [$application->startup_uuid, $application]));
    }

    public function updateInterview(Interview $interview, User $actor, array $data): void
    {
        $this->requireEditor($interview->application->startup, $actor);
        $interview->forceFill(array_intersect_key($data, array_flip(['status', 'team_notes'])))->save();
    }

    public function withdraw(JobApplication $application, User $candidate): void
    {
        if ($application->applicant_id !== $candidate->id) {
            throw new RegistryException('This is not your application.');
        }
        if ($application->isClosed()) {
            throw new RegistryException('This application is already closed.');
        }
        $from = $application->status;
        $application->forceFill(['status' => 'withdrawn', 'status_changed_at' => now()])->save();
        $application->interviews()->whereIn('status', ['proposed', 'confirmed'])->update(['status' => 'cancelled']);
        PipelineEvent::query()->create(['application_uuid' => $application->uuid, 'from_status' => $from, 'to_status' => 'withdrawn', 'actor_id' => $candidate->id]);
        $this->deleteApplicationCv($application);
        Audit::log('talent.withdrawn', $application);
    }

    public function invite(Vacancy $vacancy, User $actor, User $candidate, ?string $message): void
    {
        $this->requireEditor($vacancy->startup, $actor);
        if (! $vacancy->isAcceptingApplications()) {
            throw new RegistryException('Open the vacancy before inviting candidates.');
        }
        if (! TalentProfile::query()->searchable()->where('user_id', $candidate->id)->exists()) {
            throw new RegistryException('This candidate cannot be invited.');
        }
        $invitation = VacancyInvitation::query()->firstOrNew(['vacancy_uuid' => $vacancy->uuid, 'user_id' => $candidate->id]);
        if ($invitation->exists) {
            throw new RegistryException('You already invited this candidate.');
        }
        $invitation->fill(['invited_by' => $actor->id, 'message' => $message ? mb_substr(trim($message), 0, 1000) : null])->save();
        Audit::log('talent.invited', $vacancy, ['user_id' => $candidate->id]);
        $this->notify($candidate, $vacancy->startup->display_name.' invites you to apply', array_filter([
            'They think you may suit “'.$vacancy->title.'”. Applying is up to you.',
            $message ? 'Their message: '.$message : null,
        ]), route('jobs.show', $vacancy));
    }

    /** "team" or "candidate" when the user may open this application's CV. */
    public function cvAccess(User $user, JobApplication $application): ?string
    {
        if (! $application->cv_path) {
            return null;
        }
        if ($application->applicant_id === $user->id) {
            return 'candidate';
        }
        $startup = $application->startup;

        return $startup && $startup->status === 'active' && $startup->membershipOf($user)?->canEdit() ? 'team' : null;
    }

    /** Deletes application CV copies once the retention period after closing has passed. Returns how many were purged. */
    public function purgeExpiredCvs(): int
    {
        $cutoff = now()->subDays((int) config('nsp.talent.cv_retention_days', 180));
        $purged = 0;
        JobApplication::query()->whereNotNull('cv_path')
            ->where(fn ($q) => $q->where(fn ($w) => $w->whereIn('status', ['rejected', 'hired'])->where('status_changed_at', '<', $cutoff))
                ->orWhereHas('vacancy', fn ($v) => $v->whereIn('status', ['closed', 'filled', 'removed'])->where('closed_at', '<', $cutoff)))
            ->each(function (JobApplication $application) use (&$purged) {
                $this->deleteApplicationCv($application);
                $purged++;
            });

        return $purged;
    }

    public function requireEditor(?Startup $startup, User $user): StartupMembership
    {
        $membership = $startup?->status === 'active' ? $startup->membershipOf($user) : null;
        if (! $membership?->canEdit()) {
            throw new RegistryException('Only owners, founders and admins can manage hiring.');
        }

        return $membership;
    }

    private function deleteApplicationCv(JobApplication $application): void
    {
        if ($application->cv_path) {
            Storage::disk('local')->delete($application->cv_path);
            $application->forceFill(['cv_path' => null, 'cv_purged_at' => now()])->save();
        }
    }

    private function notifyTeam(?Startup $startup, string $subject, array $lines, string $url): void
    {
        if (! $startup) {
            return;
        }
        foreach (StartupMembership::query()->with('user')->where('startup_uuid', $startup->uuid)->where('status', 'active')
            ->whereIn('role', StartupMembership::EDITOR_ROLES)->get() as $member) {
            $this->notify($member->user, $subject, $lines, $url);
        }
    }

    private function notify(?User $user, string $subject, array $lines, string $url): void
    {
        app(Notifier::class)->notify($user, 'talent', $subject, array_values($lines), $url);
    }
}
