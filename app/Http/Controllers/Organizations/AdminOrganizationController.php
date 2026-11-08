<?php

namespace App\Http\Controllers\Organizations;

use App\Http\Controllers\Controller;
use App\Models\Organizations\Organization;
use App\Models\Organizations\OrganizationMembership;
use App\Models\Organizations\OrganizationStartupRelationship;
use App\Models\Registry\Startup;
use App\Organizations\OrganizationException;
use App\Organizations\OrganizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Platform staff (manage-organizations) create, verify and suspend organizations
 * and invite their first admin. Staff see relationship metadata, never portfolio data.
 */
class AdminOrganizationController extends Controller
{
    public function __construct(private readonly OrganizationService $service) {}

    public function index(Request $request): View
    {
        $organizations = Organization::query()
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('q'), fn ($q, $term) => $q->where('name', 'like', '%'.$term.'%'))
            ->withCount(['memberships as members_count' => fn ($q) => $q->where('status', 'active')])
            ->orderBy('name')->paginate(30)->withQueryString();

        return view('admin.organizations.index', compact('organizations'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        try {
            $organization = $this->service->createOrganization($request->user(), $data);
        } catch (OrganizationException $exception) {
            return back()->withInput()->withErrors(['name' => $exception->getMessage()]);
        }

        return redirect()->route('admin.organizations.show', $organization)->with('status', 'Organization created. Verify it, then invite its first admin.');
    }

    public function show(Organization $organization): View
    {
        $members = OrganizationMembership::query()->with('user')->where('organization_id', $organization->id)
            ->whereIn('status', ['active', 'invited'])->orderBy('status')->orderBy('id')->get();
        $relationships = OrganizationStartupRelationship::query()->with('startup:uuid,display_name')
            ->where('organization_id', $organization->id)->latest()->limit(100)->get();

        return view('admin.organizations.show', compact('organization', 'members', 'relationships'));
    }

    public function update(Request $request, Organization $organization): RedirectResponse
    {
        try {
            if ($request->filled('action')) {
                $request->validate(['action' => ['required', 'in:verify,suspend,reactivate']]);
                $this->service->changeStatus($organization, $request->user(), $request->input('action'));

                return back()->with('status', 'Status is now '.strtolower($organization->statusLabel()).'.');
            }
            $this->service->updateDetails($organization, $request->user(), $this->validated($request));
        } catch (OrganizationException $exception) {
            return back()->withInput()->withErrors(['organization' => $exception->getMessage()]);
        }

        return back()->with('status', 'Details saved.');
    }

    public function members(Request $request, Organization $organization): RedirectResponse
    {
        if ($request->filled('remove')) {
            $member = OrganizationMembership::query()->where('organization_id', $organization->id)->findOrFail((int) $request->input('remove'));
            try {
                $this->service->removeMember($organization, $request->user(), $member, true);
            } catch (OrganizationException $exception) {
                return back()->withErrors(['email' => $exception->getMessage()]);
            }

            return back()->with('status', 'Member removed.');
        }
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'string', Rule::in(array_keys(config('organizations.roles')))],
        ]);
        try {
            $this->service->inviteMember($organization, $request->user(), $data['email'], $data['role'], true);
        } catch (OrganizationException $exception) {
            return back()->withInput()->withErrors(['email' => $exception->getMessage()]);
        }

        return back()->with('status', 'Invitation sent to '.$data['email'].'.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'type' => ['required', 'string', Rule::in(array_keys(config('organizations.types')))],
            'country' => ['nullable', 'string', Rule::in(array_keys(Startup::COUNTRIES))],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
