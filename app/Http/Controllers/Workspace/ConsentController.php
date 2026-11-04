<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Registry\DataSharingConsent;
use App\Models\Registry\Startup;
use App\Registry\RegistryException;
use App\Registry\RegistryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConsentController extends Controller
{
    use ResolvesMembership;

    public function __construct(private readonly RegistryService $registry) {}

    public function index(Request $request, Startup $startup): View
    {
        $membership = $this->membershipOr404($request, $startup);
        $active = [];
        foreach (array_keys(config('nsp.registry.consent_purposes')) as $purpose) {
            $active[$purpose] = $this->registry->activeConsent($startup, $purpose);
        }
        $history = DataSharingConsent::query()->with('granter', 'revoker')->where('startup_uuid', $startup->uuid)->latest('granted_at')->limit(50)->get();

        return view('workspace.startups.consents', compact('startup', 'membership', 'active', 'history'));
    }

    public function update(Request $request, Startup $startup, string $purpose): RedirectResponse
    {
        $this->membershipOr404($request, $startup);
        $grant = $request->boolean('grant');
        try {
            $grant
                ? $this->registry->grantConsent($startup, $request->user(), $purpose)
                : $this->registry->revokeConsent($startup, $request->user(), $purpose);
        } catch (RegistryException $exception) {
            return back()->withErrors(['consent' => $exception->getMessage()]);
        }

        return back()->with('status', $grant ? 'Sharing turned on.' : 'Sharing turned off. It stops from now on.');
    }
}
