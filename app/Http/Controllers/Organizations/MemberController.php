<?php

namespace App\Http\Controllers\Organizations;

use App\Http\Controllers\Controller;
use App\Models\Organizations\Organization;
use App\Models\Organizations\OrganizationMembership;
use App\Organizations\CurrentOrganization;
use App\Organizations\OrganizationException;
use App\Organizations\OrganizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Org admins invite and remove members and change roles; invitations are accepted here too. */
class MemberController extends Controller
{
    use ResolvesOrganization;

    public function __construct(private readonly OrganizationService $service) {}

    public function index(Request $request, Organization $organization): View
    {
        $membership = $this->membershipOr404($request, $organization);
        $members = OrganizationMembership::query()->with('user', 'inviter')->where('organization_id', $organization->id)
            ->whereIn('status', ['active', 'invited'])->orderBy('status')->orderBy('id')->get();

        return view('organizations.members', compact('organization', 'membership', 'members'));
    }

    public function invite(Request $request, Organization $organization): RedirectResponse
    {
        $this->membershipOr404($request, $organization);
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'string', 'in:'.implode(',', array_keys(config('organizations.roles')))],
        ]);
        try {
            $this->service->inviteMember($organization, $request->user(), $data['email'], $data['role']);
        } catch (OrganizationException $exception) {
            return back()->withInput()->withErrors(['email' => $exception->getMessage()]);
        }

        return back()->with('status', 'Invitation sent.');
    }

    public function updateRole(Request $request, Organization $organization, OrganizationMembership $member): RedirectResponse
    {
        $this->membershipOr404($request, $organization);
        abort_unless($member->organization_id === $organization->id, 404);
        $data = $request->validate(['role' => ['required', 'string', 'in:'.implode(',', array_keys(config('organizations.roles')))]]);
        try {
            $this->service->changeRole($organization, $request->user(), $member, $data['role']);
        } catch (OrganizationException $exception) {
            return back()->withErrors(['role' => $exception->getMessage()]);
        }

        return back()->with('status', 'Role updated.');
    }

    public function remove(Request $request, Organization $organization, OrganizationMembership $member): RedirectResponse
    {
        $this->membershipOr404($request, $organization);
        abort_unless($member->organization_id === $organization->id, 404);
        try {
            $this->service->removeMember($organization, $request->user(), $member);
        } catch (OrganizationException $exception) {
            return back()->withErrors(['member' => $exception->getMessage()]);
        }
        if ($member->user_id === $request->user()->id) {
            return redirect()->route('organizations.index')->with('status', 'You left '.$organization->name.'.');
        }

        return back()->with('status', 'Member removed. Their access ended immediately.');
    }

    public function showInvitation(Request $request, string $token): View
    {
        $invite = $this->service->findInvitation($token);
        $valid = $invite && ! $invite->invite_expires_at?->isPast() && ! $invite->organization?->isSuspended();
        $emailMatches = $valid && strcasecmp((string) $invite->invited_email, (string) $request->user()->email) === 0;

        return view('organizations.invitation', compact('invite', 'valid', 'emailMatches', 'token'));
    }

    public function acceptInvitation(Request $request, string $token): RedirectResponse
    {
        try {
            $membership = $this->service->acceptInvitation($token, $request->user());
        } catch (OrganizationException $exception) {
            return redirect()->route('organizations.index')->withErrors(['invite' => $exception->getMessage()]);
        }
        CurrentOrganization::switchTo($membership->organization);

        return redirect()->route('organizations.show', $membership->organization)->with('status', 'Welcome to '.$membership->organization->name.'.');
    }

    public function declineInvitation(Request $request, string $token): RedirectResponse
    {
        $this->service->declineInvitation($token, $request->user());

        return redirect()->route('organizations.index')->with('status', 'Invitation declined.');
    }
}
