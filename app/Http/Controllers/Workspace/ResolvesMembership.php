<?php

namespace App\Http\Controllers\Workspace;

use App\Models\Registry\Startup;
use App\Models\Registry\StartupMembership;
use Illuminate\Http\Request;

/**
 * Tenant isolation for workspace pages: a startup is only visible to its
 * active members. Everyone else gets "not found", so changing the UUID in
 * the address does not even reveal that a startup exists.
 */
trait ResolvesMembership
{
    protected function membershipOr404(Request $request, Startup $startup): StartupMembership
    {
        abort_unless($startup->status === 'active', 404);
        $membership = $startup->membershipOf($request->user());
        abort_unless($membership !== null, 404);

        return $membership;
    }
}
