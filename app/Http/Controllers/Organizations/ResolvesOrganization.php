<?php

namespace App\Http\Controllers\Organizations;

use App\Models\Organizations\Organization;
use App\Models\Organizations\OrganizationMembership;
use Illuminate\Http\Request;

/** Tenant isolation for the organization portal: non-members get "not found", never "forbidden". */
trait ResolvesOrganization
{
    protected function membershipOr404(Request $request, Organization $organization): OrganizationMembership
    {
        $membership = $organization->membershipOf($request->user());
        abort_unless($membership !== null, 404);

        return $membership;
    }
}
