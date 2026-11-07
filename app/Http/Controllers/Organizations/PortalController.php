<?php

namespace App\Http\Controllers\Organizations;

use App\Http\Controllers\Controller;
use App\Models\Organizations\Organization;
use App\Models\Organizations\OrganizationMembership;
use App\Models\Organizations\OrganizationStartupRelationship;
use App\Models\Registry\Startup;
use App\Organizations\CurrentOrganization;
use App\Organizations\OrganizationException;
use App\Organizations\OrganizationService;
use App\Organizations\PortfolioAccess;
use App\Organizations\PortfolioSnapshot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Organization portal: my organizations, the tenant switcher, and the scope-filtered portfolio. */
class PortalController extends Controller
{
    use ResolvesOrganization;

    public function __construct(private readonly OrganizationService $service, private readonly PortfolioAccess $access) {}

    public function index(Request $request): View
    {
        $memberships = OrganizationMembership::query()->with('organization')->where('user_id', $request->user()->id)
            ->where('status', 'active')->orderBy('accepted_at')->get();
        $current = CurrentOrganization::membership($request->user());

        return view('organizations.index', compact('memberships', 'current'));
    }

    public function switch(Request $request, Organization $organization): RedirectResponse
    {
        $this->membershipOr404($request, $organization);
        abort_if($organization->isSuspended(), 404);
        CurrentOrganization::switchTo($organization);

        return redirect()->route('organizations.show', $organization)->with('status', 'You are now working as '.$organization->name.'.');
    }

    public function show(Request $request, Organization $organization): View
    {
        $membership = $this->membershipOr404($request, $organization);
        $portfolio = collect();
        $requests = collect();
        if ($organization->isActive() && $membership->may('view_portfolio')) {
            $portfolio = OrganizationStartupRelationship::query()->effective()->with('startup')
                ->where('organization_id', $organization->id)
                ->whereHas('startup', fn ($q) => $q->where('status', 'active'))
                ->latest('decided_at')->get();
            $requests = OrganizationStartupRelationship::query()->with('startup')
                ->where('organization_id', $organization->id)->where('status', 'requested')->latest()->get();
        }

        return view('organizations.show', compact('organization', 'membership', 'portfolio', 'requests'));
    }

    public function startup(Request $request, Organization $organization, Startup $startup, PortfolioSnapshot $snapshot): View
    {
        $membership = $this->membershipOr404($request, $organization);
        abort_unless($membership->may('view_portfolio'), 404);
        $relationship = $this->access->activeRelationship($organization, $startup);
        abort_unless($relationship !== null, 404);

        $sections = $snapshot->for($startup, (array) $relationship->scopes);

        return view('organizations.startup', compact('organization', 'membership', 'startup', 'relationship', 'sections'));
    }

    public function requestAccess(Request $request, Organization $organization): RedirectResponse
    {
        $this->membershipOr404($request, $organization);
        $data = $request->validate([
            'startup' => ['required', 'string', 'max:80'],
            'purpose' => ['required', 'string', 'in:'.implode(',', array_keys(config('organizations.purposes')))],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['string', 'in:'.implode(',', PortfolioAccess::scopeKeys())],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $startup = $this->service->findStartup($data['startup']);
        if (! $startup) {
            return back()->withInput()->withErrors(['startup' => 'No active startup matches that Hive ID or registration number.']);
        }
        try {
            $this->service->requestAccess($organization, $request->user(), $startup, $data['purpose'], $data['scopes'], $data['note'] ?? null);
        } catch (OrganizationException $exception) {
            return back()->withInput()->withErrors(['startup' => $exception->getMessage()]);
        }

        return back()->with('status', 'Request sent. The startup’s owners and founders decide what to share.');
    }

    public function withdraw(Request $request, Organization $organization, OrganizationStartupRelationship $relationship): RedirectResponse
    {
        $this->membershipOr404($request, $organization);
        abort_unless($relationship->organization_id === $organization->id, 404);
        try {
            $this->service->withdraw($relationship, $request->user());
        } catch (OrganizationException $exception) {
            return back()->withErrors(['relationship' => $exception->getMessage()]);
        }

        return back()->with('status', 'Done. Access for this startup has ended.');
    }
}
