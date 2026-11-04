<?php

namespace App\Registry;

use App\Mail\JoinRequestReceived;
use App\Mail\StartupInvitation;
use App\Models\Registry\DataSharingConsent;
use App\Models\Registry\DuplicateCandidate;
use App\Models\Registry\OutboxEvent;
use App\Models\Registry\Startup;
use App\Models\Registry\StartupAlias;
use App\Models\Registry\StartupFieldChange;
use App\Models\Registry\StartupFounder;
use App\Models\Registry\StartupMembership;
use App\Models\Registry\StartupMerge;
use App\Models\Registry\StartupProfile;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Rules of the Hive Startup Registry. Controllers (and later the API) call
 * this service; it never trusts that the caller already checked permissions
 * for team and consent changes.
 *
 * Duplicate policy (spec §5 Journey C): a registration number or website
 * domain already used by another startup is strong evidence, so a second
 * record is refused and the user is offered "request to join". A matching
 * name alone is only a warning; if the user confirms it is a different
 * company, the pair is queued for staff review. Nothing is ever merged
 * automatically.
 */
class RegistryService
{
    public const PROFILE_FIELDS = ['sector', 'sub_sector', 'headquarters_location', 'team_size', 'revenue_band', 'traction_summary',
        'funding_raised_amount', 'funding_currency', 'contact_email', 'contact_phone'];

    /** Fields whose values never appear in change history or events. */
    public const SENSITIVE_FIELDS = ['revenue_band', 'traction_summary', 'funding_raised_amount', 'contact_phone', 'contact_email'];

    /**
     * Other active startups that look like the same company.
     *
     * @return list<array{startup: Startup, strong: bool, evidence: list<string>}>
     */
    public function possibleDuplicates(array $core, ?string $exceptUuid = null): array
    {
        $registration = Startup::normalizeRegistration($core['registration_number'] ?? null);
        $domain = Startup::domainOf($core['website'] ?? null);
        $name = Startup::normalizeName(($core['legal_name'] ?? '').' '.($core['display_name'] ?? ''));

        $query = Startup::query()->active()
            ->when($exceptUuid, fn ($q) => $q->where('uuid', '!=', $exceptUuid))
            ->where(function ($q) use ($registration, $domain, $name) {
                $q->whereRaw('1 = 0');
                if ($registration) {
                    $q->orWhere('normalized_registration', $registration);
                }
                if ($domain) {
                    $q->orWhere('website_domain', $domain);
                }
                if ($name !== '') {
                    $q->orWhere('normalized_name', $name);
                }
            });

        $matches = [];
        foreach ($query->limit(10)->get() as $startup) {
            $evidence = [];
            if ($registration && $startup->normalized_registration === $registration) {
                $evidence[] = 'Same registration number';
            }
            if ($domain && $startup->website_domain === $domain) {
                $evidence[] = 'Same website ('.$domain.')';
            }
            if ($name !== '' && $startup->normalized_name === $name) {
                $evidence[] = 'Same name';
            }
            $strong = $evidence !== [] && $evidence !== ['Same name'];
            $matches[] = ['startup' => $startup, 'strong' => $strong, 'evidence' => $evidence];
        }

        return $matches;
    }

    /** Create a startup with its creator as owner. $confirmedDifferent = user confirmed a same-name match is another company. */
    public function createStartup(User $creator, array $core, array $profile, bool $confirmedDifferent = false): Startup
    {
        $matches = $this->possibleDuplicates($core);
        foreach ($matches as $match) {
            if ($match['strong']) {
                throw new RegistryException('A startup with the same registration number or website is already registered. Ask to join it instead of creating a second profile.');
            }
        }
        if ($matches && ! $confirmedDifferent) {
            throw new RegistryException('A startup with the same name is already registered. Check whether it is yours; if it is a different company, confirm that and continue.');
        }

        return DB::transaction(function () use ($creator, $core, $profile, $matches) {
            $startup = new Startup(array_intersect_key($core, array_flip(Startup::CORE_FIELDS)));
            $startup->created_by = $creator->id;
            $startup->status = 'active';
            $startup->version = 1;
            $startup->save();

            $profileModel = new StartupProfile(array_intersect_key($profile, array_flip(array_merge(self::PROFILE_FIELDS, ['visibility']))));
            $profileModel->startup_uuid = $startup->uuid;
            $profileModel->profile_completion = $profileModel->completion($startup);
            $profileModel->save();

            StartupMembership::query()->create([
                'startup_uuid' => $startup->uuid,
                'user_id' => $creator->id,
                'user_subject' => $creator->oidc_subject,
                'role' => 'owner',
                'status' => 'active',
                'accepted_at' => now(),
            ]);
            StartupFounder::query()->create(['startup_uuid' => $startup->uuid, 'user_id' => $creator->id, 'name' => $creator->name, 'title' => 'Founder']);

            StartupFieldChange::query()->create([
                'startup_uuid' => $startup->uuid, 'field_name' => '*', 'old_value' => null, 'new_value' => 'Profile created',
                'source_system' => 'nsp', 'changed_by' => $creator->id, 'version' => 1,
            ]);
            foreach ($matches as $match) {
                $this->queueCandidate($startup, $match['startup'], 30, ['Same name (creator confirmed it is a different company)']);
            }
            OutboxEvent::record('registry.startup.created.v1', $startup->uuid, ['display_name' => $startup->display_name, 'country' => $startup->country]);
            Audit::log('registry.startup_created', $startup, ['display_name' => $startup->display_name]);

            return $startup;
        });
    }

    /**
     * Save changes if nobody else saved since $expectedVersion; otherwise throw VersionConflict.
     *
     * @return list<string> the fields that changed
     */
    public function updateStartup(Startup $startup, User $actor, array $core, array $profile, int $expectedVersion): array
    {
        $matches = array_filter($this->possibleDuplicates($core + $startup->only(Startup::CORE_FIELDS), $startup->uuid), fn ($m) => $m['strong']);
        if ($matches) {
            throw new RegistryException('Another registered startup already uses this registration number or website. Contact Hive if this is the same company.');
        }

        return DB::transaction(function () use ($startup, $actor, $core, $profile, $expectedVersion) {
            $fresh = Startup::query()->whereKey($startup->uuid)->lockForUpdate()->firstOrFail();
            if ($fresh->version !== $expectedVersion) {
                throw new VersionConflict('Someone else saved this profile while you were editing. Your changes were not saved. Reload the page to see the latest version, then make your changes again.');
            }
            $profileModel = StartupProfile::query()->firstOrNew(['startup_uuid' => $fresh->uuid]);

            $changes = [];
            foreach (array_intersect_key($core, array_flip(Startup::CORE_FIELDS)) as $field => $value) {
                if ((string) $fresh->{$field} !== (string) $value) {
                    $changes[$field] = [$fresh->{$field}, $value];
                    $fresh->{$field} = $value;
                }
            }
            foreach (array_intersect_key($profile, array_flip(array_merge(self::PROFILE_FIELDS, ['visibility']))) as $field => $value) {
                $old = $profileModel->{$field};
                if ($field === 'visibility' ? $old != $value : (string) $old !== (string) $value) {
                    $changes[$field] = [$field === 'visibility' ? json_encode($old) : $old, $field === 'visibility' ? json_encode($value) : $value];
                    $profileModel->{$field} = $value;
                }
            }
            if (! $changes) {
                return [];
            }

            $fresh->version = $fresh->version + 1;
            $fresh->save();
            $profileModel->profile_completion = $profileModel->completion($fresh);
            $profileModel->save();

            foreach ($changes as $field => [$old, $new]) {
                $sensitive = in_array($field, self::SENSITIVE_FIELDS, true);
                StartupFieldChange::query()->create([
                    'startup_uuid' => $fresh->uuid,
                    'field_name' => $field,
                    'old_value' => $sensitive ? null : ($old === null ? null : mb_substr((string) $old, 0, 2000)),
                    'new_value' => $sensitive ? '[changed]' : ($new === null ? null : mb_substr((string) $new, 0, 2000)),
                    'source_system' => 'nsp',
                    'changed_by' => $actor->id,
                    'version' => $fresh->version,
                ]);
            }
            OutboxEvent::record('registry.startup.updated.v1', $fresh->uuid, ['changed_fields' => array_keys($changes), 'version' => $fresh->version]);
            Audit::log('registry.startup_updated', $fresh, ['fields' => array_keys($changes), 'version' => $fresh->version]);
            $startup->setRawAttributes($fresh->getAttributes(), true);

            return array_keys($changes);
        });
    }

    /** Invite someone by email. Returns the plain token (only ever sent by email). */
    public function invite(Startup $startup, User $inviter, string $email, string $role): string
    {
        $membership = $this->requireMembership($startup, $inviter);
        if (! $membership->canManageTeam()) {
            throw new RegistryException('Only owners and admins can invite people.');
        }
        if (! array_key_exists($role, StartupMembership::ROLES)) {
            throw new RegistryException('Choose a valid role.');
        }
        if (in_array($role, ['owner', 'admin'], true) && $membership->role !== 'owner') {
            throw new RegistryException('Only an owner can invite another owner or admin.');
        }
        $email = Str::lower(trim($email));
        $existingUser = User::query()->where('email', $email)->first();
        if ($existingUser && $startup->membershipOf($existingUser)) {
            throw new RegistryException('This person is already on the team.');
        }

        $token = Str::random(48);
        DB::transaction(function () use ($startup, $inviter, $email, $role, $token) {
            StartupMembership::query()->where('startup_uuid', $startup->uuid)->where('status', 'invited')
                ->where('invited_email', $email)->update(['status' => 'revoked', 'revoked_at' => now(), 'invite_token_hash' => null]);
            StartupMembership::query()->create([
                'startup_uuid' => $startup->uuid,
                'role' => $role,
                'status' => 'invited',
                'invited_email' => $email,
                'invite_token_hash' => hash('sha256', $token),
                'invite_expires_at' => now()->addDays((int) config('nsp.registry.invite_days', 7)),
                'invited_by' => $inviter->id,
            ]);
            Audit::log('registry.member_invited', $startup, ['role' => $role]);
        });

        try {
            Mail::to($email)->queue(new StartupInvitation($startup->display_name, $inviter->name, StartupMembership::ROLES[$role], route('invitations.show', $token)));
        } catch (Throwable $exception) {
            Log::warning('[registry] invitation email failed: '.$exception->getMessage());
        }

        return $token;
    }

    public function findInvitation(string $token): ?StartupMembership
    {
        return StartupMembership::query()->with('startup', 'inviter')
            ->where('invite_token_hash', hash('sha256', $token))->where('status', 'invited')->first();
    }

    public function acceptInvitation(string $token, User $user): StartupMembership
    {
        return DB::transaction(function () use ($token, $user) {
            $invite = StartupMembership::query()->where('invite_token_hash', hash('sha256', $token))
                ->where('status', 'invited')->lockForUpdate()->first();
            if (! $invite || $invite->invite_expires_at?->isPast()) {
                throw new RegistryException('This invitation is no longer valid. Ask the team to send a new one.');
            }
            // The invite belongs to the email it was sent to; it cannot be used from another account.
            if (strcasecmp((string) $invite->invited_email, (string) $user->email) !== 0) {
                throw new RegistryException('This invitation was sent to a different email address. Sign in with that address to accept it.');
            }
            $startup = Startup::query()->findOrFail($invite->startup_uuid);
            if ($startup->status !== 'active') {
                throw new RegistryException('This startup profile is no longer active.');
            }
            if ($startup->membershipOf($user)) {
                $invite->update(['status' => 'declined', 'invite_token_hash' => null]);
                throw new RegistryException('You are already on this team.');
            }
            $invite->update([
                'user_id' => $user->id, 'user_subject' => $user->oidc_subject, 'status' => 'active',
                'accepted_at' => now(), 'invite_token_hash' => null,
            ]);
            OutboxEvent::record('registry.membership.changed.v1', $startup->uuid, ['membership_uuid' => $invite->uuid, 'role' => $invite->role, 'status' => 'active']);
            Audit::log('registry.invitation_accepted', $startup, ['role' => $invite->role]);

            return $invite;
        });
    }

    public function declineInvitation(string $token, User $user): void
    {
        $invite = $this->findInvitation($token);
        if ($invite && strcasecmp((string) $invite->invited_email, (string) $user->email) === 0) {
            $invite->update(['status' => 'declined', 'invite_token_hash' => null]);
        }
    }

    public function requestToJoin(Startup $startup, User $user, string $message): StartupMembership
    {
        if ($startup->status !== 'active') {
            throw new RegistryException('This startup profile is not active.');
        }
        if ($startup->membershipOf($user)) {
            throw new RegistryException('You are already on this team.');
        }
        $pending = $startup->memberships()->where('user_id', $user->id)->where('status', 'requested')->first();
        if ($pending) {
            return $pending;
        }
        $request = StartupMembership::query()->create([
            'startup_uuid' => $startup->uuid, 'user_id' => $user->id, 'user_subject' => $user->oidc_subject,
            'role' => 'team_member', 'status' => 'requested', 'request_message' => mb_substr(trim($message), 0, 1000),
        ]);
        Audit::log('registry.join_requested', $startup);

        $managers = $startup->activeMemberships()->whereIn('role', StartupMembership::TEAM_MANAGER_ROLES)->with('user')->get();
        foreach ($managers as $manager) {
            try {
                Mail::to($manager->user->email)->queue(new JoinRequestReceived($startup->display_name, $user->name, route('workspace.startups.team', $startup)));
            } catch (Throwable $exception) {
                Log::warning('[registry] join request email failed: '.$exception->getMessage());
            }
        }

        return $request;
    }

    public function decideRequest(Startup $startup, User $actor, StartupMembership $request, bool $approve, string $role = 'team_member'): void
    {
        $membership = $this->requireMembership($startup, $actor);
        if (! $membership->canManageTeam() || $request->startup_uuid !== $startup->uuid || $request->status !== 'requested') {
            throw new RegistryException('You cannot decide on this request.');
        }
        if ($approve && in_array($role, ['owner', 'admin'], true) && $membership->role !== 'owner') {
            throw new RegistryException('Only an owner can make someone an owner or admin.');
        }
        DB::transaction(function () use ($startup, $request, $approve, $role) {
            $request->update($approve
                ? ['status' => 'active', 'role' => array_key_exists($role, StartupMembership::ROLES) ? $role : 'team_member', 'accepted_at' => now()]
                : ['status' => 'declined']);
            if ($approve) {
                OutboxEvent::record('registry.membership.changed.v1', $startup->uuid, ['membership_uuid' => $request->uuid, 'role' => $request->role, 'status' => 'active']);
            }
            Audit::log($approve ? 'registry.join_approved' : 'registry.join_declined', $startup);
        });
    }

    public function changeRole(Startup $startup, User $actor, StartupMembership $target, string $role): void
    {
        $membership = $this->requireMembership($startup, $actor);
        if (! array_key_exists($role, StartupMembership::ROLES) || $target->startup_uuid !== $startup->uuid || $target->status !== 'active') {
            throw new RegistryException('Choose a valid team member and role.');
        }
        $touchesSenior = in_array($role, ['owner', 'admin'], true) || in_array($target->role, ['owner', 'admin'], true);
        if (! $membership->canManageTeam() || ($touchesSenior && $membership->role !== 'owner')) {
            throw new RegistryException('Only an owner can change owner or admin roles.');
        }
        if ($target->role === 'owner' && $role !== 'owner') {
            $this->ensureAnotherOwner($startup, $target);
        }
        $target->update(['role' => $role]);
        OutboxEvent::record('registry.membership.changed.v1', $startup->uuid, ['membership_uuid' => $target->uuid, 'role' => $role, 'status' => 'active']);
        Audit::log('registry.role_changed', $startup, ['role' => $role]);
    }

    /** Remove a member, cancel an invitation, or leave (when $target is the actor). */
    public function removeMember(Startup $startup, User $actor, StartupMembership $target): void
    {
        if ($target->startup_uuid !== $startup->uuid) {
            throw new RegistryException('This person is not on this team.');
        }
        $leaving = $target->user_id === $actor->id;
        if (! $leaving) {
            $membership = $this->requireMembership($startup, $actor);
            if (! $membership->canManageTeam() || (in_array($target->role, ['owner', 'admin'], true) && $membership->role !== 'owner')) {
                throw new RegistryException('You cannot remove this person.');
            }
        }
        if ($target->status === 'active' && $target->role === 'owner') {
            $this->ensureAnotherOwner($startup, $target);
        }
        $target->update(['status' => 'revoked', 'revoked_at' => now(), 'invite_token_hash' => null]);
        OutboxEvent::record('registry.membership.changed.v1', $startup->uuid, ['membership_uuid' => $target->uuid, 'status' => 'revoked']);
        Audit::log($leaving ? 'registry.member_left' : 'registry.member_removed', $startup);
    }

    public function grantConsent(Startup $startup, User $actor, string $purpose): DataSharingConsent
    {
        $definition = $this->consentPurpose($purpose);
        $this->requireConsentRights($startup, $actor);
        $existing = $this->activeConsent($startup, $purpose);
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($startup, $actor, $purpose, $definition) {
            $consent = DataSharingConsent::query()->create([
                'startup_uuid' => $startup->uuid, 'purpose' => $purpose, 'recipient_system' => $definition['recipient'],
                'scope' => $definition['scope'], 'granted_by' => $actor->id, 'granted_at' => now(),
                'policy_version' => config('nsp.registry.consent_policy_version'),
            ]);
            OutboxEvent::record('registry.consent.granted.v1', $startup->uuid, ['purpose' => $purpose, 'recipient_system' => $definition['recipient']]);
            Audit::log('registry.consent_granted', $startup, ['purpose' => $purpose]);

            return $consent;
        });
    }

    public function revokeConsent(Startup $startup, User $actor, string $purpose): void
    {
        $definition = $this->consentPurpose($purpose);
        $this->requireConsentRights($startup, $actor);
        DB::transaction(function () use ($startup, $actor, $purpose, $definition) {
            $count = DataSharingConsent::query()->where('startup_uuid', $startup->uuid)->where('purpose', $purpose)
                ->whereNull('revoked_at')->update(['revoked_at' => now(), 'revoked_by' => $actor->id]);
            if ($count > 0) {
                // Downstream systems must stop using the data from now on (spec §7).
                OutboxEvent::record('registry.consent.revoked.v1', $startup->uuid, ['purpose' => $purpose, 'recipient_system' => $definition['recipient']]);
                Audit::log('registry.consent_revoked', $startup, ['purpose' => $purpose]);
            }
        });
    }

    public function activeConsent(Startup $startup, string $purpose): ?DataSharingConsent
    {
        return DataSharingConsent::query()->where('startup_uuid', $startup->uuid)->where('purpose', $purpose)->whereNull('revoked_at')->first();
    }

    /** Find pairs of active startups that may be the same company. Returns the number of new candidates. */
    public function scanForDuplicates(): int
    {
        $created = 0;
        foreach (['normalized_registration' => [90, 'Same registration number'], 'website_domain' => [70, 'Same website'], 'normalized_name' => [30, 'Same name']] as $column => [$score, $label]) {
            $groups = Startup::query()->active()->whereNotNull($column)->where($column, '!=', '')
                ->select($column)->groupBy($column)->havingRaw('COUNT(*) > 1')->pluck($column);
            foreach ($groups as $value) {
                $members = Startup::query()->active()->where($column, $value)->orderBy('created_at')->get();
                for ($i = 0; $i < $members->count(); $i++) {
                    for ($j = $i + 1; $j < $members->count(); $j++) {
                        $created += $this->queueCandidate($members[$i], $members[$j], $score, [$label]) ? 1 : 0;
                    }
                }
            }
        }

        return $created;
    }

    public function dismissCandidate(DuplicateCandidate $candidate, User $actor, string $notes): void
    {
        $candidate->update(['status' => 'not_duplicate', 'decided_by' => $actor->id, 'decided_at' => now(), 'notes' => mb_substr($notes, 0, 2000)]);
        Audit::log('registry.duplicate_dismissed', 'startup_duplicate_candidates', ['candidate_id' => $candidate->id]);
    }

    /** Merge $source into $target. Every move is journaled so undoMerge() can reverse it. */
    public function merge(Startup $source, Startup $target, User $actor, string $reason, ?DuplicateCandidate $candidate = null): StartupMerge
    {
        if ($source->uuid === $target->uuid || $source->status !== 'active' || $target->status !== 'active') {
            throw new RegistryException('Both startups must be active and different.');
        }
        if (trim($reason) === '') {
            throw new RegistryException('Write down the evidence that these are the same company.');
        }

        return DB::transaction(function () use ($source, $target, $actor, $reason, $candidate) {
            $journal = ['memberships_moved' => [], 'memberships_revoked' => [], 'founders_moved' => [], 'aliases_moved' => []];
            foreach ($source->memberships()->whereIn('status', ['active', 'invited', 'requested'])->get() as $membership) {
                $duplicate = $membership->user_id && $target->memberships()->where('user_id', $membership->user_id)->whereIn('status', ['active', 'requested'])->exists();
                if ($duplicate) {
                    $journal['memberships_revoked'][] = ['uuid' => $membership->uuid, 'status' => $membership->status];
                    $membership->update(['status' => 'revoked', 'revoked_at' => now()]);
                } else {
                    $journal['memberships_moved'][] = $membership->uuid;
                    $membership->update(['startup_uuid' => $target->uuid]);
                }
            }
            foreach ($source->founders()->get() as $founder) {
                $journal['founders_moved'][] = $founder->uuid;
                $founder->update(['startup_uuid' => $target->uuid]);
            }
            foreach ($source->aliases()->get() as $alias) {
                $journal['aliases_moved'][] = $alias->id;
                $alias->update(['startup_uuid' => $target->uuid]);
            }
            // Old references to the source UUID keep resolving to the target.
            $redirect = StartupAlias::query()->create(['source_system' => 'registry', 'external_id' => $source->uuid, 'startup_uuid' => $target->uuid, 'created_by' => $actor->id]);
            $journal['redirect_alias_id'] = $redirect->id;
            // Consents are NOT moved: sharing agreed for one record must not widen to another.

            $source->forceFill(['status' => 'merged', 'merged_into_uuid' => $target->uuid, 'version' => $source->version + 1])->save();
            $merge = StartupMerge::query()->create([
                'source_uuid' => $source->uuid, 'target_uuid' => $target->uuid, 'candidate_id' => $candidate?->id,
                'journal' => $journal, 'reason' => mb_substr($reason, 0, 2000), 'merged_by' => $actor->id, 'merged_at' => now(),
            ]);
            $candidate?->update(['status' => 'merged', 'decided_by' => $actor->id, 'decided_at' => now(), 'notes' => mb_substr($reason, 0, 2000)]);
            OutboxEvent::record('registry.startup.merged.v1', $target->uuid, ['source_uuid' => $source->uuid, 'target_uuid' => $target->uuid]);
            Audit::log('registry.startups_merged', $target, ['source_uuid' => $source->uuid]);

            return $merge;
        });
    }

    public function undoMerge(StartupMerge $merge, User $actor): void
    {
        if ($merge->undone_at) {
            throw new RegistryException('This merge was already undone.');
        }
        DB::transaction(function () use ($merge, $actor) {
            $journal = $merge->journal;
            StartupMembership::query()->whereIn('uuid', $journal['memberships_moved'])->update(['startup_uuid' => $merge->source_uuid]);
            foreach ($journal['memberships_revoked'] as $row) {
                StartupMembership::query()->whereKey($row['uuid'])->update(['status' => $row['status'], 'revoked_at' => null]);
            }
            StartupFounder::query()->whereIn('uuid', $journal['founders_moved'])->update(['startup_uuid' => $merge->source_uuid]);
            StartupAlias::query()->whereIn('id', $journal['aliases_moved'])->update(['startup_uuid' => $merge->source_uuid]);
            StartupAlias::query()->whereKey($journal['redirect_alias_id'] ?? 0)->delete();
            $source = Startup::query()->findOrFail($merge->source_uuid);
            $source->forceFill(['status' => 'active', 'merged_into_uuid' => null, 'version' => $source->version + 1])->save();
            if ($merge->candidate_id) {
                DuplicateCandidate::query()->whereKey($merge->candidate_id)->update(['status' => 'pending', 'decided_by' => null, 'decided_at' => null]);
            }
            $merge->update(['undone_by' => $actor->id, 'undone_at' => now()]);
            OutboxEvent::record('registry.startup.unmerged.v1', $merge->source_uuid, ['source_uuid' => $merge->source_uuid, 'target_uuid' => $merge->target_uuid]);
            Audit::log('registry.merge_undone', $source, ['target_uuid' => $merge->target_uuid]);
        });
    }

    private function queueCandidate(Startup $a, Startup $b, int $score, array $evidence): bool
    {
        [$first, $second] = strcmp($a->uuid, $b->uuid) < 0 ? [$a, $b] : [$b, $a];
        $existing = DuplicateCandidate::query()->where('startup_a_uuid', $first->uuid)->where('startup_b_uuid', $second->uuid)->first();
        if ($existing) {
            // A new kind of evidence for the same pair strengthens it; re-scanning the same evidence changes nothing.
            $newEvidence = array_diff($evidence, $existing->evidence);
            if ($existing->status === 'pending' && $newEvidence) {
                $existing->update([
                    'score' => min(100, max($existing->score, $score) + 10),
                    'evidence' => array_values(array_merge($existing->evidence, $newEvidence)),
                ]);
            }

            return false;
        }
        DuplicateCandidate::query()->create(['startup_a_uuid' => $first->uuid, 'startup_b_uuid' => $second->uuid, 'score' => $score, 'evidence' => $evidence]);

        return true;
    }

    private function requireMembership(Startup $startup, User $user): StartupMembership
    {
        $membership = $startup->membershipOf($user);
        if (! $membership) {
            throw new RegistryException('You are not on this startup’s team.');
        }

        return $membership;
    }

    private function requireConsentRights(Startup $startup, User $actor): void
    {
        if (! $this->requireMembership($startup, $actor)->canManageConsent()) {
            throw new RegistryException('Only owners and founders can change data-sharing choices.');
        }
    }

    private function consentPurpose(string $purpose): array
    {
        $definition = config('nsp.registry.consent_purposes.'.$purpose);
        if (! is_array($definition)) {
            throw new RegistryException('Unknown data-sharing purpose.');
        }

        return $definition;
    }

    private function ensureAnotherOwner(Startup $startup, StartupMembership $target): void
    {
        $owners = $startup->activeMemberships()->where('role', 'owner')->where('uuid', '!=', $target->uuid)->count();
        if ($owners === 0) {
            throw new RegistryException('A startup must always have at least one owner. Make someone else an owner first.');
        }
    }
}
