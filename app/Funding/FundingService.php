<?php

namespace App\Funding;

use App\Ecosystem\Eligibility;
use App\Mail\FundingNotice;
use App\Models\Funding\DataRoomFile;
use App\Models\Funding\DataRoomGrant;
use App\Models\Funding\FundingRequest;
use App\Models\Funding\Introduction;
use App\Models\Funding\InvestorProfile;
use App\Models\Growth\VgiFramework;
use App\Models\Registry\DataSharingConsent;
use App\Models\Registry\Startup;
use App\Models\Registry\StartupMembership;
use App\Models\User;
use App\Registry\RegistryException;
use App\Support\Audit;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Privacy rules for funding (decision D5, option a):
 * - investors are verified by Hive before they see anything;
 * - a startup is visible to investors only with an open funding request AND
 *   the "investor_listing" consent, and only the fields in that consent;
 * - data-room files are visible to the startup's team, and to an investor
 *   only while that investor has an active grant from the startup;
 * - introductions need the other side to accept before contacts are shared.
 */
class FundingService
{
    public function investorProfile(?User $user): ?InvestorProfile
    {
        $profile = $user ? InvestorProfile::query()->where('user_id', $user->id)->first() : null;

        return $profile && $profile->isVerified() ? $profile : null;
    }

    public function openRequest(Startup $startup): ?FundingRequest
    {
        return FundingRequest::query()->where('startup_uuid', $startup->uuid)->where('status', 'open')->latest('id')->first();
    }

    public function listingConsent(Startup $startup): ?DataSharingConsent
    {
        return DataSharingConsent::query()->where('startup_uuid', $startup->uuid)->where('purpose', 'investor_listing')->whereNull('revoked_at')->first();
    }

    /** Can verified investors find this startup in their list? */
    public function isListed(Startup $startup): bool
    {
        return $startup->status === 'active' && $this->openRequest($startup) !== null && $this->listingConsent($startup) !== null;
    }

    /** May this investor open the startup's funding page? */
    public function investorMaySee(User $investor, Startup $startup): bool
    {
        if (! $this->investorProfile($investor) || $startup->status !== 'active') {
            return false;
        }

        return $this->isListed($startup)
            || $this->activeGrant($startup, $investor) !== null
            || $this->isConnected($startup, $investor);
    }

    public function isConnected(Startup $startup, User $investor): bool
    {
        return Introduction::query()->where('startup_uuid', $startup->uuid)->where('investor_user_id', $investor->id)->where('status', 'accepted')->exists();
    }

    /** Stage names investors can choose: the bands of the approved growth rubric. */
    public static function stageOptions(): array
    {
        return collect(VgiFramework::current()?->definition['bands'] ?? [])->pluck('label')->filter()->values()->all();
    }

    public function activeGrant(Startup $startup, User $investor): ?DataRoomGrant
    {
        return DataRoomGrant::query()->active()->where('startup_uuid', $startup->uuid)->where('investor_user_id', $investor->id)->first();
    }

    /** "team", "investor", or null when the user may not open the file. */
    public function downloadAccess(User $user, DataRoomFile $file): ?string
    {
        $startup = Startup::query()->find($file->startup_uuid);
        if (! $startup || $startup->status !== 'active') {
            return null;
        }
        if ($startup->membershipOf($user)) {
            return 'team';
        }
        if ($this->investorProfile($user) && $this->activeGrant($startup, $user)) {
            return 'investor';
        }

        return null;
    }

    /**
     * Explainable fit between an investor's thesis and a funding request.
     *
     * @return array{fits: bool, reasons: list<string>, misses: list<string>}
     */
    public function fit(InvestorProfile $investor, Startup $startup, FundingRequest $request): array
    {
        $reasons = [];
        $misses = [];
        $sector = $startup->profile?->sector;
        if ($investor->sectors) {
            in_array($sector, $investor->sectors, true) ? $reasons[] = 'Sector: '.$sector : $misses[] = 'Sector';
        }
        if ($investor->countries) {
            in_array($startup->country, $investor->countries, true) ? $reasons[] = 'Country' : $misses[] = 'Country';
        }
        if ($investor->stages) {
            $stage = Eligibility::stage($startup);
            if ($stage && in_array($stage, $investor->stages, true)) {
                $reasons[] = 'Stage: '.$stage;
            } elseif ($stage) {
                $misses[] = 'Stage';
            }
        }
        if ($investor->instruments) {
            $shared = array_intersect($investor->instruments, $request->instruments ?? []);
            $shared ? $reasons[] = 'Instrument: '.implode(', ', array_map(fn ($i) => InvestorProfile::INSTRUMENTS[$i] ?? $i, $shared)) : $misses[] = 'Instrument';
        }
        if (($investor->ticket_min || $investor->ticket_max) && $investor->currency === $request->currency) {
            $inRange = (! $investor->ticket_min || $request->amount >= $investor->ticket_min) && (! $investor->ticket_max || $request->amount <= $investor->ticket_max);
            $inRange ? $reasons[] = 'Amount within ticket size' : $misses[] = 'Ticket size';
        }

        return ['fits' => $misses === [], 'reasons' => $reasons, 'misses' => $misses];
    }

    public function requestIntroduction(Startup $startup, User $founder, User $investor, string $message): Introduction
    {
        $membership = $startup->membershipOf($founder);
        if (! $membership?->canEdit()) {
            throw new RegistryException('Only owners, founders and admins can contact investors.');
        }
        $profile = $this->investorProfile($investor);
        if (! $profile || ! $profile->is_listed) {
            throw new RegistryException('This investor is not available on NSP.');
        }

        return $this->createIntroduction($startup, $investor, 'founder', $founder, $message);
    }

    public function expressInterest(Startup $startup, User $investor, string $message): Introduction
    {
        if (! $this->investorProfile($investor) || ! $this->isListed($startup)) {
            throw new RegistryException('This startup is not open to investors right now.');
        }

        return $this->createIntroduction($startup, $investor, 'investor', $investor, $message);
    }

    public function decide(Introduction $introduction, User $user, bool $accept): void
    {
        if ($introduction->status !== 'requested') {
            throw new RegistryException('This introduction was already answered.');
        }
        // Only the side that did not ask can answer.
        $allowed = $introduction->requested_by === 'founder'
            ? $introduction->investor_user_id === $user->id
            : (bool) Startup::query()->find($introduction->startup_uuid)?->membershipOf($user)?->canEdit();
        if (! $allowed) {
            throw new RegistryException('You cannot answer this introduction.');
        }
        $introduction->update(['status' => $accept ? 'accepted' : 'declined', 'decided_by' => $user->id, 'decided_at' => now()]);
        Audit::log($accept ? 'funding.introduction_accepted' : 'funding.introduction_declined', $introduction);
        if ($introduction->requester) {
            $this->notify($introduction->requester, $accept ? 'Introduction accepted' : 'Introduction declined', [
                ($introduction->requested_by === 'founder' ? 'The investor' : $introduction->startup->display_name).($accept ? ' accepted your introduction. You can now see each other’s contact details on NSP.' : ' declined your introduction.'),
            ], $introduction->requested_by === 'founder' ? route('workspace.funding', $introduction->startup_uuid) : route('investor.dashboard'));
        }
    }

    public function grant(Startup $startup, User $founder, User $investor, ?string $expiresAt): DataRoomGrant
    {
        if (! $startup->membershipOf($founder)?->canEdit()) {
            throw new RegistryException('Only owners, founders and admins can share the data room.');
        }
        if (! $this->investorProfile($investor)) {
            throw new RegistryException('Documents can only be shared with Hive-verified investors.');
        }
        if (! $this->isConnected($startup, $investor)) {
            throw new RegistryException('Connect with the investor first, then share your data room.');
        }
        DataRoomGrant::query()->active()->where('startup_uuid', $startup->uuid)->where('investor_user_id', $investor->id)
            ->update(['revoked_at' => now(), 'revoked_by' => $founder->id]);
        $grant = DataRoomGrant::query()->create([
            'startup_uuid' => $startup->uuid, 'investor_user_id' => $investor->id, 'granted_by' => $founder->id,
            'expires_at' => $expiresAt ?: now()->addDays(30),
        ]);
        Audit::log('funding.data_room_granted', $startup, ['investor_user_id' => $investor->id, 'expires_at' => (string) $grant->expires_at]);
        $this->notify($investor, $startup->display_name.' shared its data room with you', [
            'You can view their documents until '.$grant->expires_at->format('j F Y').'. Downloads are logged and visible to the startup.',
        ], route('investor.startup', $startup));

        return $grant;
    }

    public function revoke(Startup $startup, User $founder, DataRoomGrant $grant): void
    {
        if (! $startup->membershipOf($founder)?->canEdit() || $grant->startup_uuid !== $startup->uuid) {
            throw new RegistryException('You cannot change this.');
        }
        $grant->update(['revoked_at' => now(), 'revoked_by' => $founder->id]);
        Audit::log('funding.data_room_revoked', $startup, ['investor_user_id' => $grant->investor_user_id]);
    }

    private function createIntroduction(Startup $startup, User $investor, string $side, User $requester, string $message): Introduction
    {
        $existing = Introduction::query()->where('startup_uuid', $startup->uuid)->where('investor_user_id', $investor->id)
            ->whereIn('status', ['requested', 'accepted'])->first();
        if ($existing) {
            throw new RegistryException($existing->status === 'accepted' ? 'You are already connected.' : 'An introduction is already waiting for a reply.');
        }
        $introduction = Introduction::query()->create([
            'startup_uuid' => $startup->uuid, 'investor_user_id' => $investor->id, 'requested_by' => $side,
            'requester_user_id' => $requester->id, 'message' => mb_substr(trim($message), 0, 2000), 'status' => 'requested',
        ]);
        Audit::log('funding.introduction_requested', $introduction, ['by' => $side]);

        if ($side === 'founder') {
            $this->notify($investor, $startup->display_name.' would like an introduction', [
                'A founder of '.$startup->display_name.' asked to connect with you on NSP.',
            ], route('investor.dashboard'));
        } else {
            foreach (StartupMembership::query()->with('user')->where('startup_uuid', $startup->uuid)->where('status', 'active')->whereIn('role', StartupMembership::EDITOR_ROLES)->get() as $member) {
                $this->notify($member->user, 'An investor is interested in '.$startup->display_name, [
                    'A Hive-verified investor expressed interest in your funding request. Review it and decide whether to connect.',
                ], route('workspace.funding', $startup));
            }
        }

        return $introduction;
    }

    private function notify(?User $user, string $subject, array $lines, string $url): void
    {
        if (! $user?->is_active) {
            return;
        }
        try {
            Mail::to($user->email)->queue(new FundingNotice($subject, $lines, $url));
        } catch (Throwable $exception) {
            Log::warning('[funding] notice failed: '.$exception->getMessage());
        }
    }
}
