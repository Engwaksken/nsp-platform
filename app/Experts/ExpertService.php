<?php

namespace App\Experts;

use App\Mail\ServiceNotice;
use App\Models\Experts\Engagement;
use App\Models\Experts\ExpertProfile;
use App\Models\Experts\ExpertReview;
use App\Models\Experts\ExpertSession;
use App\Models\Experts\SessionAction;
use App\Models\Registry\Startup;
use App\Models\Registry\StartupMembership;
use App\Models\User;
use App\Registry\RegistryException;
use App\Support\Audit;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Rules for experts and mentors:
 * - experts are verified by Hive before founders can find or ask them;
 * - an expert sees a startup only through an engagement (a founder's request or a
 *   staff mentor assignment), and only its name, sector and what was written for them;
 * - contact details are shared once the engagement is active;
 * - session notes are shared, expert-only or startup-only, as labelled;
 * - review comments go to the expert and Hive staff; the directory shows an average
 *   only once there are enough reviews.
 */
class ExpertService
{
    public function expertProfile(?User $user): ?ExpertProfile
    {
        $profile = $user ? ExpertProfile::query()->where('user_id', $user->id)->first() : null;

        return $profile && $profile->isVerified() ? $profile : null;
    }

    /** @return array{expert: bool, membership: ?StartupMembership} or null when the user has no part in it. */
    public function sideOf(User $user, Engagement $engagement): ?array
    {
        $startup = $engagement->startup;
        if (! $startup || $startup->status !== 'active') {
            return null;
        }
        $isExpert = $engagement->expert_user_id === $user->id;
        $membership = $startup->membershipOf($user);
        if (! $isExpert && ! $membership) {
            return null;
        }

        return ['expert' => $isExpert, 'membership' => $membership];
    }

    /**
     * Why this expert may suit this startup.
     *
     * @param  list<string>  $areas  areas the founder is looking for
     * @return list<string>
     */
    public function reasons(ExpertProfile $expert, Startup $startup, array $areas = []): array
    {
        $reasons = [];
        $sector = $startup->profile?->sector;
        if ($sector && in_array($sector, $expert->sectors ?? [], true)) {
            $reasons[] = 'Knows '.$sector;
        }
        $shared = array_intersect($expert->expertise ?? [], $areas);
        if ($shared) {
            $reasons[] = 'Expertise: '.implode(', ', array_intersect_key(ExpertProfile::areas(), array_flip($shared)));
        }
        if ($expert->offers_free) {
            $reasons[] = 'Free sessions';
        }

        return $reasons;
    }

    /** @return array<int, array{average: float, count: int}> keyed by expert user id, only where enough reviews exist. */
    public function ratings(array $expertUserIds): array
    {
        $min = (int) config('nsp.experts.min_reviews_shown', 3);

        return ExpertReview::query()->whereIn('expert_user_id', $expertUserIds)
            ->selectRaw('expert_user_id, avg(rating) as average, count(*) as total')->groupBy('expert_user_id')->get()
            ->filter(fn ($row) => $row->total >= $min)
            ->mapWithKeys(fn ($row) => [$row->expert_user_id => ['average' => round((float) $row->average, 1), 'count' => (int) $row->total]])
            ->all();
    }

    public function activeCount(User $expert): int
    {
        return Engagement::query()->where('expert_user_id', $expert->id)->where('status', 'active')->count();
    }

    public function request(Startup $startup, User $founder, User $expert, array $data): Engagement
    {
        if (! $startup->membershipOf($founder)?->canEdit()) {
            throw new RegistryException('Only owners, founders and admins can ask experts for help.');
        }
        $profile = $this->expertProfile($expert);
        if (! $profile || ! $profile->is_listed) {
            throw new RegistryException('This expert is not available on NSP.');
        }
        if ($data['type'] === 'mentorship' && ! $profile->offersMentoring()) {
            throw new RegistryException('This expert does not offer mentoring.');
        }
        $this->refuseDuplicate($startup, $expert);

        $engagement = Engagement::query()->create([
            'startup_uuid' => $startup->uuid, 'expert_user_id' => $expert->id, 'type' => $data['type'], 'source' => 'founder_request',
            'requested_by' => $founder->id, 'topic' => $data['topic'], 'details' => $data['details'] ?? null,
            'preferred_times' => $data['preferred_times'] ?? null, 'status' => 'requested',
        ]);
        Audit::log('experts.requested', $engagement, ['type' => $engagement->type]);
        $this->notify($expert, $startup->display_name.' asked for your help', [
            'Topic: '.$engagement->topic,
            'Open NSP to read the request and accept or decline it.',
        ], route('engagements.show', $engagement));

        return $engagement;
    }

    public function decide(Engagement $engagement, User $expert, bool $accept, ?string $note): void
    {
        if ($engagement->expert_user_id !== $expert->id || ! $this->expertProfile($expert)) {
            throw new RegistryException('Only the expert can answer this request.');
        }
        if ($engagement->status !== 'requested') {
            throw new RegistryException('This request was already answered.');
        }
        $engagement->update(['status' => $accept ? 'active' : 'declined', 'response_note' => $note, 'decided_by' => $expert->id, 'decided_at' => now()]);
        Audit::log($accept ? 'experts.accepted' : 'experts.declined', $engagement);
        $this->notifyTeam($engagement->startup, $accept ? 'An expert accepted your request' : 'An expert declined your request', [
            $expert->name.($accept ? ' accepted “'.$engagement->topic.'”. You can now see each other’s contact details and plan a session.' : ' cannot help with “'.$engagement->topic.'” right now.'),
        ], route('engagements.show', $engagement));
    }

    public function assignMentor(Startup $startup, User $staff, User $expert, string $topic, ?string $details): Engagement
    {
        $profile = $this->expertProfile($expert);
        if (! $profile || ! $profile->offersMentoring()) {
            throw new RegistryException('Choose a verified expert who offers mentoring.');
        }
        if ($startup->status !== 'active') {
            throw new RegistryException('This startup is not active.');
        }
        $this->refuseDuplicate($startup, $expert);

        $engagement = Engagement::query()->create([
            'startup_uuid' => $startup->uuid, 'expert_user_id' => $expert->id, 'type' => 'mentorship', 'source' => 'staff_assignment',
            'requested_by' => $staff->id, 'topic' => $topic, 'details' => $details, 'status' => 'active',
            'decided_by' => $staff->id, 'decided_at' => now(),
        ]);
        Audit::log('experts.mentor_assigned', $engagement, ['expert_user_id' => $expert->id]);
        $this->notify($expert, 'Hive matched you with '.$startup->display_name, ['Mentorship focus: '.$topic], route('engagements.show', $engagement));
        $this->notifyTeam($startup, 'Hive assigned you a mentor', [$expert->name.' will mentor you on: '.$topic], route('engagements.show', $engagement));

        return $engagement;
    }

    public function schedule(Engagement $engagement, User $user, array $data): ExpertSession
    {
        $side = $this->requireParticipant($engagement, $user, editorOnly: true);
        if ($engagement->status !== 'active') {
            throw new RegistryException('Sessions can be planned only while the engagement is active.');
        }
        $session = ExpertSession::query()->create([
            'engagement_uuid' => $engagement->uuid, 'scheduled_at' => $data['scheduled_at'], 'duration_minutes' => $data['duration_minutes'],
            'mode' => $data['mode'], 'location' => $data['location'] ?? null, 'status' => 'scheduled', 'created_by' => $user->id,
        ]);
        Audit::log('experts.session_scheduled', $engagement);
        $text = 'Session on '.$session->scheduled_at->format('j F Y, H:i').' ('.ExpertSession::MODES[$session->mode].', '.$session->duration_minutes.' minutes).';
        $side['expert']
            ? $this->notifyTeam($engagement->startup, 'Session planned with '.$user->name, [$text], route('engagements.show', $engagement))
            : $this->notify($engagement->expert, 'Session planned with '.$engagement->startup->display_name, [$text], route('engagements.show', $engagement));

        return $session;
    }

    /** Status and notes. Each side writes only the notes it owns. */
    public function updateSession(ExpertSession $session, User $user, array $data): void
    {
        $side = $this->requireParticipant($session->engagement, $user, editorOnly: true);
        $changes = [];
        if (array_key_exists('shared_notes', $data)) {
            $changes['shared_notes'] = $data['shared_notes'];
        }
        if ($side['expert'] && array_key_exists('private_notes', $data)) {
            $changes['expert_notes'] = $data['private_notes'];
        } elseif (! $side['expert'] && array_key_exists('private_notes', $data)) {
            $changes['startup_notes'] = $data['private_notes'];
        }
        if (! empty($data['status'])) {
            $changes['status'] = $data['status'];
        }
        $session->forceFill($changes)->save();
    }

    public function addAction(Engagement $engagement, User $user, array $data): SessionAction
    {
        $this->requireParticipant($engagement, $user, editorOnly: true);
        if (! $engagement->isOpen()) {
            throw new RegistryException('This engagement is closed.');
        }

        return SessionAction::query()->create($data + ['engagement_uuid' => $engagement->uuid, 'created_by' => $user->id]);
    }

    public function toggleAction(SessionAction $action, User $user): void
    {
        $this->requireParticipant($action->engagement, $user, editorOnly: true);
        $action->update(['done_at' => $action->done_at ? null : now()]);
    }

    public function complete(Engagement $engagement, User $user, ?string $outcome): void
    {
        $side = $this->requireParticipant($engagement, $user, editorOnly: true);
        if ($engagement->status !== 'active') {
            throw new RegistryException('Only an active engagement can be completed.');
        }
        $engagement->update(['status' => 'completed', 'completed_at' => now(), 'outcome' => $outcome]);
        Audit::log('experts.completed', $engagement);
        if ($side['expert']) {
            $this->notifyTeam($engagement->startup, 'Engagement completed', [
                $user->name.' marked “'.$engagement->topic.'” as completed. Please tell us how it went.',
            ], route('engagements.show', $engagement));
        }
    }

    public function cancel(Engagement $engagement, User $user): void
    {
        $side = $this->requireParticipant($engagement, $user, editorOnly: true);
        if (! $engagement->isOpen()) {
            throw new RegistryException('This engagement is already closed.');
        }
        $engagement->update(['status' => 'cancelled']);
        Audit::log('experts.cancelled', $engagement);
        $side['expert']
            ? $this->notifyTeam($engagement->startup, 'Engagement cancelled', [$user->name.' cancelled “'.$engagement->topic.'”.'], route('engagements.show', $engagement))
            : $this->notify($engagement->expert, 'Engagement cancelled', [$engagement->startup->display_name.' cancelled “'.$engagement->topic.'”.'], route('engagements.show', $engagement));
    }

    public function review(Engagement $engagement, User $user, array $data): ExpertReview
    {
        $side = $this->requireParticipant($engagement, $user, editorOnly: true);
        if ($side['expert']) {
            throw new RegistryException('Experts cannot review themselves.');
        }
        if ($engagement->status !== 'completed') {
            throw new RegistryException('You can give feedback once the engagement is completed.');
        }
        if ($engagement->review()->exists()) {
            throw new RegistryException('Feedback was already given.');
        }
        $review = ExpertReview::query()->create([
            'engagement_uuid' => $engagement->uuid, 'startup_uuid' => $engagement->startup_uuid, 'expert_user_id' => $engagement->expert_user_id,
            'reviewer_id' => $user->id, 'rating' => $data['rating'], 'comment' => $data['comment'] ?? null, 'helped' => $data['helped'] ?? null,
        ]);
        Audit::log('experts.reviewed', $engagement, ['rating' => $review->rating]);
        $this->notify($engagement->expert, 'You received feedback', [$engagement->startup->display_name.' shared feedback on “'.$engagement->topic.'”.'], route('engagements.show', $engagement));

        return $review;
    }

    /** @return array{expert: bool, membership: ?StartupMembership} */
    private function requireParticipant(Engagement $engagement, User $user, bool $editorOnly = false): array
    {
        $side = $this->sideOf($user, $engagement);
        if (! $side) {
            throw new RegistryException('You are not part of this engagement.');
        }
        if ($side['expert'] && ! $this->expertProfile($user)) {
            throw new RegistryException('Your expert profile is not active.');
        }
        if ($editorOnly && ! $side['expert'] && ! $side['membership']?->canEdit()) {
            throw new RegistryException('Only owners, founders and admins can do this.');
        }

        return $side;
    }

    private function refuseDuplicate(Startup $startup, User $expert): void
    {
        $open = Engagement::query()->where('startup_uuid', $startup->uuid)->where('expert_user_id', $expert->id)
            ->whereIn('status', ['requested', 'active'])->exists();
        if ($open) {
            throw new RegistryException('There is already an open engagement with this expert.');
        }
    }

    private function notifyTeam(?Startup $startup, string $subject, array $lines, string $url): void
    {
        if (! $startup) {
            return;
        }
        $members = StartupMembership::query()->with('user')->where('startup_uuid', $startup->uuid)->where('status', 'active')
            ->whereIn('role', StartupMembership::EDITOR_ROLES)->get();
        foreach ($members as $member) {
            $this->notify($member->user, $subject, $lines, $url);
        }
    }

    private function notify(?User $user, string $subject, array $lines, string $url): void
    {
        if (! $user?->is_active) {
            return;
        }
        try {
            Mail::to($user->email)->queue(new ServiceNotice($subject, $lines, $url));
        } catch (Throwable $exception) {
            Log::warning('[experts] notice failed: '.$exception->getMessage());
        }
    }
}
