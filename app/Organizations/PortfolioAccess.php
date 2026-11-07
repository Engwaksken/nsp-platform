<?php

namespace App\Organizations;

use App\Models\Organizations\Organization;
use App\Models\Organizations\OrganizationStartupRelationship;
use App\Models\Registry\Startup;
use App\Models\User;

/**
 * The one question other modules ask before showing startup data to an
 * organization: is there an ACTIVE, unexpired relationship that grants this
 * scope? Evaluated on every call (no caching), so a revocation or expiry takes
 * effect on the very next request.
 *
 * Stable API (dashboards, analytics, builder, capital modules call this):
 *  - activeRelationship(Organization, Startup): ?OrganizationStartupRelationship
 *  - canView(Organization, Startup, scope): bool
 *  - startupUuids(Organization, scope): list<string>
 *  - userCanView(User, Organization, Startup, scope): bool  (also checks the user's org role)
 */
class PortfolioAccess
{
    public function activeRelationship(Organization $org, Startup $startup): ?OrganizationStartupRelationship
    {
        if (! $org->isActive() || $startup->status !== 'active') {
            return null;
        }

        return OrganizationStartupRelationship::query()->effective()
            ->where('organization_id', $org->id)->where('startup_uuid', $startup->uuid)
            ->latest('decided_at')->first();
    }

    public function canView(Organization $org, Startup $startup, string $scope): bool
    {
        $relationship = $this->activeRelationship($org, $startup);

        return $relationship !== null && in_array($scope, (array) $relationship->scopes, true);
    }

    /** @return list<string> UUIDs of active startups the organization may see for this scope */
    public function startupUuids(Organization $org, string $scope): array
    {
        if (! $org->isActive()) {
            return [];
        }

        return OrganizationStartupRelationship::query()->effective()
            ->where('organization_id', $org->id)
            ->whereHas('startup', fn ($q) => $q->where('status', 'active'))
            ->get(['startup_uuid', 'scopes'])
            ->filter(fn ($r) => in_array($scope, (array) $r->scopes, true))
            ->pluck('startup_uuid')->unique()->values()->all();
    }

    /** Same as canView, and the user must be an active member whose role includes portfolio access. */
    public function userCanView(?User $user, Organization $org, Startup $startup, string $scope): bool
    {
        $membership = $org->membershipOf($user);

        return $membership !== null && $membership->may('view_portfolio') && $this->canView($org, $startup, $scope);
    }

    /** @return list<string> */
    public static function scopeKeys(): array
    {
        return array_keys((array) config('organizations.scopes', []));
    }
}
