<?php

namespace App\Organizations;

use App\Communication\Notifier;
use App\Models\Organizations\Organization;
use App\Models\Organizations\OrganizationMembership;
use App\Models\Organizations\OrganizationStartupRelationship;
use App\Models\Registry\Startup;
use App\Models\Registry\StartupMembership;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Every change to organizations, memberships and portfolio relationships goes
 * through here, so the rules and the audit trail live in one place.
 */
class OrganizationService
{
    public function __construct(private readonly Notifier $notifier) {}

    /* ---------------------------------------------------------------- organizations (platform staff) */

    public function createOrganization(User $actor, array $data): Organization
    {
        $this->assertType($data['type'] ?? '');
        $organization = Organization::query()->create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name']),
            'type' => $data['type'],
            'country' => $data['country'] ?? null,
            'website' => $data['website'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => 'pending',
            'created_by' => $actor->id,
        ]);
        Audit::log('organizations.created', $organization, ['name' => $organization->name, 'type' => $organization->type]);

        return $organization;
    }

    public function updateDetails(Organization $organization, User $actor, array $data): void
    {
        $this->assertType($data['type'] ?? $organization->type);
        $organization->fill(array_intersect_key($data, array_flip(['name', 'type', 'country', 'website', 'description'])));
        $changed = array_keys($organization->getDirty());
        if ($changed) {
            $organization->save();
            Audit::log('organizations.updated', $organization, ['fields' => $changed]);
        }
    }

    /** verify | suspend | reactivate */
    public function changeStatus(Organization $organization, User $actor, string $action): void
    {
        match ($action) {
            'verify' => $organization->forceFill(['status' => 'active', 'verified_at' => now(), 'verified_by' => $actor->id, 'suspended_at' => null])->save(),
            'suspend' => $organization->forceFill(['status' => 'suspended', 'suspended_at' => now()])->save(),
            'reactivate' => $this->reactivate($organization),
            default => throw new OrganizationException('Choose a valid action.'),
        };
        Audit::log('organizations.'.['verify' => 'verified', 'suspend' => 'suspended', 'reactivate' => 'reactivated'][$action], $organization, ['status' => $organization->status]);
    }

    private function reactivate(Organization $organization): void
    {
        if ($organization->verified_at === null) {
            throw new OrganizationException('Verify the organization first.');
        }
        $organization->forceFill(['status' => 'active', 'suspended_at' => null])->save();
    }

    /* ---------------------------------------------------------------- memberships */

    /** Invite someone by email. Returns the plain token (only ever sent by email). */
    public function inviteMember(Organization $organization, User $inviter, string $email, string $role, bool $asStaff = false): string
    {
        if (! $asStaff) {
            $this->requireRight($organization, $inviter, 'manage_members');
        }
        if ($organization->isSuspended()) {
            throw new OrganizationException('This organization is suspended.');
        }
        $this->assertRole($role);
        $email = Str::lower(trim($email));
        $existing = User::query()->where('email', $email)->first();
        if ($existing && $organization->membershipOf($existing)) {
            throw new OrganizationException('This person is already a member.');
        }

        $token = Str::random(48);
        $days = (int) config('organizations.invite_days', 7);
        DB::transaction(function () use ($organization, $inviter, $email, $role, $token, $days) {
            OrganizationMembership::query()->where('organization_id', $organization->id)->where('status', 'invited')
                ->where('invited_email', $email)->update(['status' => 'revoked', 'revoked_at' => now(), 'invite_token_hash' => null, 'active_key' => null]);
            OrganizationMembership::query()->create([
                'organization_id' => $organization->id,
                'role' => $role,
                'status' => 'invited',
                'invited_email' => $email,
                'invite_token_hash' => hash('sha256', $token),
                'invite_expires_at' => now()->addDays($days),
                'invited_by' => $inviter->id,
                'active_key' => OrganizationMembership::inviteKey($organization->id, $email),
            ]);
            Audit::log('organizations.member_invited', $organization, ['role' => $role, 'email_hash' => Audit::hashIdentifier($email)]);
        });

        try {
            Mail::to($email)->queue(new OrganizationInvitation($organization->name, $inviter->name, config('organizations.roles.'.$role), route('organizations.invitations.show', $token), $days));
        } catch (Throwable $exception) {
            Log::warning('[organizations] invitation email failed: '.$exception->getMessage());
        }

        return $token;
    }

    public function findInvitation(string $token): ?OrganizationMembership
    {
        return OrganizationMembership::query()->with('organization', 'inviter')
            ->where('invite_token_hash', hash('sha256', $token))->where('status', 'invited')->first();
    }

    public function acceptInvitation(string $token, User $user): OrganizationMembership
    {
        return DB::transaction(function () use ($token, $user) {
            $invite = OrganizationMembership::query()->where('invite_token_hash', hash('sha256', $token))
                ->where('status', 'invited')->lockForUpdate()->first();
            if (! $invite || $invite->invite_expires_at?->isPast()) {
                throw new OrganizationException('This invitation is no longer valid. Ask the organization to send a new one.');
            }
            if (strcasecmp((string) $invite->invited_email, (string) $user->email) !== 0) {
                throw new OrganizationException('This invitation was sent to a different email address. Sign in with that address to accept it.');
            }
            $organization = Organization::query()->findOrFail($invite->organization_id);
            if ($organization->isSuspended()) {
                throw new OrganizationException('This organization is suspended.');
            }
            if ($organization->membershipOf($user)) {
                $invite->update(['status' => 'declined', 'invite_token_hash' => null, 'active_key' => null]);
                throw new OrganizationException('You are already a member of this organization.');
            }
            $invite->update([
                'user_id' => $user->id, 'status' => 'active', 'accepted_at' => now(), 'invite_token_hash' => null,
                'active_key' => OrganizationMembership::activeKey($organization->id, $user->id),
            ]);
            Audit::log('organizations.member_joined', $organization, ['membership_id' => $invite->id, 'role' => $invite->role]);

            return $invite;
        });
    }

    public function declineInvitation(string $token, User $user): void
    {
        $invite = $this->findInvitation($token);
        if ($invite && strcasecmp((string) $invite->invited_email, (string) $user->email) === 0) {
            $invite->update(['status' => 'declined', 'invite_token_hash' => null, 'active_key' => null]);
            Audit::log('organizations.invitation_declined', $invite->organization, ['membership_id' => $invite->id]);
        }
    }

    public function changeRole(Organization $organization, User $actor, OrganizationMembership $target, string $role, bool $asStaff = false): void
    {
        $this->assertSameOrganization($organization, $target);
        $this->assertRole($role);
        if (! $asStaff) {
            $this->requireRight($organization, $actor, 'manage_members');
            // Nobody changes their own role: no self-escalation, no accidental lock-out.
            if ($target->user_id === $actor->id) {
                throw new OrganizationException('You cannot change your own role. Ask another organization admin.');
            }
        }
        if ($target->status !== 'active') {
            throw new OrganizationException('Only active members have a role to change.');
        }
        if ($target->role === 'org_admin' && $role !== 'org_admin') {
            $this->ensureAnotherAdmin($organization, $target);
        }
        $from = $target->role;
        $target->update(['role' => $role]);
        Audit::log('organizations.member_role_changed', $organization, ['membership_id' => $target->id, 'from' => $from, 'to' => $role]);
    }

    public function removeMember(Organization $organization, User $actor, OrganizationMembership $target, bool $asStaff = false): void
    {
        $this->assertSameOrganization($organization, $target);
        $leaving = $target->user_id !== null && $target->user_id === $actor->id;
        if (! $asStaff && ! $leaving) {
            $this->requireRight($organization, $actor, 'manage_members');
        }
        if (! in_array($target->status, ['active', 'invited'], true)) {
            throw new OrganizationException('This membership has already ended.');
        }
        if ($target->status === 'active' && $target->role === 'org_admin') {
            $this->ensureAnotherAdmin($organization, $target);
        }
        $target->update(['status' => 'revoked', 'revoked_at' => now(), 'invite_token_hash' => null, 'active_key' => null]);
        Audit::log($leaving ? 'organizations.member_left' : 'organizations.member_removed', $organization, ['membership_id' => $target->id]);
    }

    /* ---------------------------------------------------------------- portfolio relationships */

    /** Find an active startup by its Hive UUID or its registration number. */
    public function findStartup(string $reference): ?Startup
    {
        $reference = trim($reference);
        if ($reference === '') {
            return null;
        }
        if (Str::isUuid($reference)) {
            return Startup::query()->active()->find(strtolower($reference));
        }
        $normalized = Startup::normalizeRegistration($reference);

        return $normalized ? Startup::query()->active()->where('normalized_registration', $normalized)->first() : null;
    }

    /** An organization asks a startup for access; nothing is visible until the startup approves. */
    public function requestAccess(Organization $organization, User $actor, Startup $startup, string $purpose, array $scopes, ?string $note): OrganizationStartupRelationship
    {
        $this->requireRight($organization, $actor, 'request_access');
        $this->requireActiveOrganization($organization);
        $this->assertPurpose($purpose);
        $scopes = $this->cleanScopes($scopes);

        $relationship = DB::transaction(function () use ($organization, $actor, $startup, $purpose, $scopes, $note) {
            $this->closeExpired($organization, $startup);
            if ($this->openRelationship($organization, $startup)) {
                throw new OrganizationException('There is already an open request or active access for this startup.');
            }
            $relationship = OrganizationStartupRelationship::query()->create([
                'organization_id' => $organization->id, 'startup_uuid' => $startup->uuid, 'purpose' => $purpose, 'scopes' => $scopes,
                'status' => 'requested', 'initiated_by' => 'organization', 'requested_by' => $actor->id, 'note' => $note,
                'open_key' => OrganizationStartupRelationship::openKey($organization->id, $startup->uuid),
            ]);
            Audit::log('organizations.access_requested', $relationship, ['organization_id' => $organization->id, 'startup_uuid' => $startup->uuid, 'purpose' => $purpose, 'scopes' => $scopes]);

            return $relationship;
        });

        foreach ($this->consentHolders($startup) as $holder) {
            $this->notifier->notify($holder, 'organizations', $organization->name.' asked to follow '.$startup->display_name,
                [$organization->name.' ('.$organization->typeLabel().') asked for access to some of your startup information.', 'Nothing is shared until an owner or founder approves.'],
                route('workspace.organizations', $startup));
        }

        return $relationship;
    }

    /** A startup shares with an organization on its own initiative. Active immediately. */
    public function share(Startup $startup, User $actor, Organization $organization, string $purpose, array $scopes, ?Carbon $expiresAt, ?string $note): OrganizationStartupRelationship
    {
        $this->requireConsentRights($startup, $actor);
        $this->requireActiveOrganization($organization);
        $this->assertPurpose($purpose);
        $scopes = $this->cleanScopes($scopes);
        $this->assertFuture($expiresAt);

        return DB::transaction(function () use ($startup, $actor, $organization, $purpose, $scopes, $expiresAt, $note) {
            $this->closeExpired($organization, $startup);
            if ($this->openRelationship($organization, $startup)) {
                throw new OrganizationException('This organization already has an open request or active access. Approve, decline or revoke it first.');
            }
            $relationship = OrganizationStartupRelationship::query()->create([
                'organization_id' => $organization->id, 'startup_uuid' => $startup->uuid, 'purpose' => $purpose, 'scopes' => $scopes,
                'status' => 'active', 'initiated_by' => 'startup', 'requested_by' => $actor->id, 'decided_by' => $actor->id,
                'decided_at' => now(), 'expires_at' => $expiresAt, 'note' => $note,
                'open_key' => OrganizationStartupRelationship::openKey($organization->id, $startup->uuid),
            ]);
            Audit::log('organizations.access_shared', $relationship, ['organization_id' => $organization->id, 'startup_uuid' => $startup->uuid, 'scopes' => $scopes, 'expires_at' => $expiresAt?->toDateString()]);

            return $relationship;
        });
    }

    /** Owners and founders approve (optionally narrowing scopes and setting an end date) or decline. */
    public function decide(OrganizationStartupRelationship $relationship, User $actor, bool $approve, array $scopes = [], ?Carbon $expiresAt = null): void
    {
        $startup = $relationship->startup;
        $this->requireConsentRights($startup, $actor);
        if ($relationship->status !== 'requested') {
            throw new OrganizationException('This request has already been decided.');
        }
        if ($approve) {
            $granted = array_values(array_intersect((array) $relationship->scopes, $this->cleanScopes($scopes)));
            if (! $granted) {
                throw new OrganizationException('Choose at least one of the requested items to share.');
            }
            $this->assertFuture($expiresAt);
            $relationship->update(['status' => 'active', 'scopes' => $granted, 'decided_by' => $actor->id, 'decided_at' => now(), 'expires_at' => $expiresAt]);
            Audit::log('organizations.access_granted', $relationship, ['organization_id' => $relationship->organization_id, 'startup_uuid' => $startup->uuid, 'scopes' => $granted, 'expires_at' => $expiresAt?->toDateString()]);
        } else {
            $relationship->update(['status' => 'declined', 'decided_by' => $actor->id, 'decided_at' => now(), 'open_key' => null]);
            Audit::log('organizations.access_declined', $relationship, ['organization_id' => $relationship->organization_id, 'startup_uuid' => $startup->uuid]);
        }

        $this->notifier->notify($relationship->requester, 'organizations',
            $startup->display_name.($approve ? ' approved' : ' declined').' your access request',
            [$approve ? 'You can now see the items the startup chose to share.' : 'The startup chose not to share information at this time.'],
            route('organizations.show', $relationship->organization));
    }

    /** Startup owners/founders end access. Takes effect on the next request. */
    public function revoke(OrganizationStartupRelationship $relationship, User $actor): void
    {
        $this->requireConsentRights($relationship->startup, $actor);
        if ($relationship->status !== 'active') {
            throw new OrganizationException('This access is not active.');
        }
        $relationship->update(['status' => 'revoked', 'revoked_at' => now(), 'revoked_by' => $actor->id, 'open_key' => null]);
        Audit::log('organizations.access_revoked', $relationship, ['organization_id' => $relationship->organization_id, 'startup_uuid' => $relationship->startup_uuid]);
    }

    /** The organization withdraws its own request or gives up access. */
    public function withdraw(OrganizationStartupRelationship $relationship, User $actor): void
    {
        $this->requireRight($relationship->organization, $actor, 'request_access');
        if (! $relationship->isOpen()) {
            throw new OrganizationException('This request or access has already ended.');
        }
        $relationship->update(['status' => 'withdrawn', 'revoked_at' => now(), 'revoked_by' => $actor->id, 'open_key' => null]);
        Audit::log('organizations.access_withdrawn', $relationship, ['organization_id' => $relationship->organization_id, 'startup_uuid' => $relationship->startup_uuid]);
    }

    /* ---------------------------------------------------------------- helpers */

    public function openRelationship(Organization $organization, Startup $startup): ?OrganizationStartupRelationship
    {
        return OrganizationStartupRelationship::query()->where('open_key', OrganizationStartupRelationship::openKey($organization->id, $startup->uuid))->first();
    }

    /** Active grants past their end date are closed so a new request can be made. */
    private function closeExpired(Organization $organization, Startup $startup): void
    {
        $open = $this->openRelationship($organization, $startup);
        if ($open && $open->status === 'active' && ! $open->isEffective()) {
            $open->update(['status' => 'expired', 'open_key' => null]);
            Audit::log('organizations.access_expired', $open, ['organization_id' => $organization->id, 'startup_uuid' => $startup->uuid]);
        }
    }

    /** @return list<User> active owners and founders of the startup */
    private function consentHolders(Startup $startup): array
    {
        return StartupMembership::query()->with('user')->where('startup_uuid', $startup->uuid)->where('status', 'active')
            ->whereIn('role', StartupMembership::CONSENT_ROLES)->get()->pluck('user')->filter()->values()->all();
    }

    private function requireRight(Organization $organization, User $actor, string $right): OrganizationMembership
    {
        $membership = $organization->membershipOf($actor);
        if (! $membership || ! $membership->may($right)) {
            throw new OrganizationException('Your role in this organization does not allow this.');
        }

        return $membership;
    }

    private function requireConsentRights(Startup $startup, User $actor): void
    {
        $membership = $startup->membershipOf($actor);
        if (! $membership || ! $membership->canManageConsent()) {
            throw new OrganizationException('Only the startup’s owners and founders can decide what organizations may see.');
        }
    }

    private function requireActiveOrganization(Organization $organization): void
    {
        if (! $organization->isActive()) {
            throw new OrganizationException('This organization is not verified and active.');
        }
    }

    private function ensureAnotherAdmin(Organization $organization, OrganizationMembership $target): void
    {
        $others = OrganizationMembership::query()->where('organization_id', $organization->id)->where('status', 'active')
            ->where('role', 'org_admin')->whereKeyNot($target->id)->count();
        if ($others === 0) {
            throw new OrganizationException('An organization needs at least one admin. Make someone else an admin first.');
        }
    }

    private function assertSameOrganization(Organization $organization, OrganizationMembership $target): void
    {
        if ($target->organization_id !== $organization->id) {
            abort(404);
        }
    }

    private function assertRole(string $role): void
    {
        if (! array_key_exists($role, (array) config('organizations.roles'))) {
            throw new OrganizationException('Choose a valid role.');
        }
    }

    private function assertType(string $type): void
    {
        if (! array_key_exists($type, (array) config('organizations.types'))) {
            throw new OrganizationException('Choose a valid organization type.');
        }
    }

    private function assertPurpose(string $purpose): void
    {
        if (! array_key_exists($purpose, (array) config('organizations.purposes'))) {
            throw new OrganizationException('Choose a valid purpose.');
        }
    }

    private function assertFuture(?Carbon $date): void
    {
        if ($date && ! $date->isFuture()) {
            throw new OrganizationException('The end date must be in the future.');
        }
    }

    /** @return list<string> */
    private function cleanScopes(array $scopes): array
    {
        $clean = array_values(array_intersect(PortfolioAccess::scopeKeys(), array_map('strval', $scopes)));
        if (! $clean) {
            throw new OrganizationException('Choose at least one item to share.');
        }

        return $clean;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'organization';
        $base = Str::limit($base, 100, '');
        $slug = $base;
        $n = 2;
        while (Organization::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }
}
