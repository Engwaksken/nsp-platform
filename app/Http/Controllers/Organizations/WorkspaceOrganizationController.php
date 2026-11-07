<?php

namespace App\Http\Controllers\Organizations;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Workspace\ResolvesMembership;
use App\Models\Organizations\Organization;
use App\Models\Organizations\OrganizationStartupRelationship;
use App\Models\Registry\Startup;
use App\Organizations\OrganizationException;
use App\Organizations\OrganizationService;
use App\Organizations\PortfolioAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** The startup side: who asked, who can see what, approve / decline / revoke / share. */
class WorkspaceOrganizationController extends Controller
{
    use ResolvesMembership;

    public function __construct(private readonly OrganizationService $service) {}

    public function index(Request $request, Startup $startup): View
    {
        $membership = $this->membershipOr404($request, $startup);
        $relationships = OrganizationStartupRelationship::query()->with('organization', 'requester', 'decider')
            ->where('startup_uuid', $startup->uuid)->latest()->limit(100)->get();
        $organizations = $membership->canManageConsent()
            ? Organization::query()->where('status', 'active')->orderBy('name')->get(['id', 'uuid', 'name', 'type'])
            : collect();

        return view('workspace.organizations', [
            'startup' => $startup,
            'membership' => $membership,
            'pending' => $relationships->where('status', 'requested'),
            'active' => $relationships->filter(fn ($r) => $r->isEffective()),
            'history' => $relationships->reject(fn ($r) => $r->status === 'requested' || $r->isEffective()),
            'organizations' => $organizations,
            'defaultExpiry' => now()->addDays((int) config('organizations.default_grant_days', 365))->toDateString(),
        ]);
    }

    public function decide(Request $request, Startup $startup, OrganizationStartupRelationship $relationship): RedirectResponse
    {
        $this->membershipOr404($request, $startup);
        abort_unless($relationship->startup_uuid === $startup->uuid, 404);
        $data = $request->validate([
            'decision' => ['required', 'in:approve,decline'],
            'scopes' => ['array'],
            'scopes.*' => ['string', 'in:'.implode(',', PortfolioAccess::scopeKeys())],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);
        $approve = $data['decision'] === 'approve';
        try {
            $this->service->decide($relationship, $request->user(), $approve, $data['scopes'] ?? [], $this->date($data['expires_at'] ?? null));
        } catch (OrganizationException $exception) {
            return back()->withErrors(['decision' => $exception->getMessage()]);
        }

        return back()->with('status', $approve ? 'Approved. The organization can now see only what you ticked.' : 'Declined. Nothing was shared.');
    }

    public function revoke(Request $request, Startup $startup, OrganizationStartupRelationship $relationship): RedirectResponse
    {
        $this->membershipOr404($request, $startup);
        abort_unless($relationship->startup_uuid === $startup->uuid, 404);
        try {
            $this->service->revoke($relationship, $request->user());
        } catch (OrganizationException $exception) {
            return back()->withErrors(['revoke' => $exception->getMessage()]);
        }

        return back()->with('status', 'Access revoked. It stopped immediately.');
    }

    public function share(Request $request, Startup $startup): RedirectResponse
    {
        $this->membershipOr404($request, $startup);
        $data = $request->validate([
            'organization' => ['required', 'string', 'exists:organizations,uuid'],
            'purpose' => ['required', 'string', 'in:'.implode(',', array_keys(config('organizations.purposes')))],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['string', 'in:'.implode(',', PortfolioAccess::scopeKeys())],
            'expires_at' => ['nullable', 'date', 'after:today'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $organization = Organization::query()->where('uuid', $data['organization'])->firstOrFail();
        try {
            $this->service->share($startup, $request->user(), $organization, $data['purpose'], $data['scopes'], $this->date($data['expires_at'] ?? null), $data['note'] ?? null);
        } catch (OrganizationException $exception) {
            return back()->withInput()->withErrors(['share' => $exception->getMessage()]);
        }

        return back()->with('status', 'Shared with '.$organization->name.'.');
    }

    private function date(?string $value): ?Carbon
    {
        return $value ? Carbon::parse($value)->endOfDay() : null;
    }
}
