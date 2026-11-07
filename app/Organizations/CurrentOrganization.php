<?php

namespace App\Organizations;

use App\Models\Organizations\Organization;
use App\Models\Organizations\OrganizationMembership;
use App\Models\User;

/**
 * The organization a user is currently acting for (the tenant switcher).
 * Re-checked on every request like App\Registry\ActiveStartup, so removing a
 * member or suspending an organization takes effect immediately even if the
 * session still points at it.
 */
class CurrentOrganization
{
    private const SESSION_KEY = 'current_organization_id';

    public static function membership(?User $user): ?OrganizationMembership
    {
        if (! $user) {
            return null;
        }
        $id = session(self::SESSION_KEY);
        $query = OrganizationMembership::query()->with('organization')
            ->where('user_id', $user->id)->where('status', 'active')
            ->whereHas('organization', fn ($q) => $q->where('status', '!=', 'suspended'));

        $membership = $id ? (clone $query)->where('organization_id', $id)->first() : null;
        if (! $membership) {
            $membership = $query->orderBy('accepted_at')->orderBy('id')->first();
            session([self::SESSION_KEY => $membership?->organization_id]);
        }

        return $membership;
    }

    public static function organization(?User $user): ?Organization
    {
        return self::membership($user)?->organization;
    }

    public static function switchTo(Organization $organization): void
    {
        session([self::SESSION_KEY => $organization->id]);
    }
}
