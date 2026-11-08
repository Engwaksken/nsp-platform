<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Registry\Startup;
use App\Models\Registry\StartupMembership;
use App\Registry\RegistryException;
use App\Registry\RegistryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeamController extends Controller
{
    use ResolvesMembership;

    public function __construct(private readonly RegistryService $registry) {}

    public function index(Request $request, Startup $startup): View
    {
        $membership = $this->membershipOr404($request, $startup);
        $members = $startup->memberships()->with('user', 'inviter')
            ->whereIn('status', $membership->canManageTeam() ? ['active', 'invited', 'requested'] : ['active'])
            ->orderByRaw("CASE status WHEN 'requested' THEN 0 WHEN 'active' THEN 1 ELSE 2 END")->orderBy('created_at')->get();

        return view('workspace.startups.team', compact('startup', 'membership', 'members'));
    }

    public function invite(Request $request, Startup $startup): RedirectResponse
    {
        $this->membershipOr404($request, $startup);
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in(array_keys(StartupMembership::ROLES))],
        ]);

        return $this->attempt(fn () => $this->registry->invite($startup, $request->user(), $data['email'], $data['role']),
            'Invitation sent to '.$data['email'].'.');
    }

    public function updateRole(Request $request, Startup $startup, StartupMembership $member): RedirectResponse
    {
        $this->membershipOr404($request, $startup);
        $data = $request->validate(['role' => ['required', Rule::in(array_keys(StartupMembership::ROLES))]]);

        return $this->attempt(fn () => $this->registry->changeRole($startup, $request->user(), $member, $data['role']), 'Role updated.');
    }

    public function remove(Request $request, Startup $startup, StartupMembership $member): RedirectResponse
    {
        $this->membershipOr404($request, $startup);
        $leaving = $member->user_id === $request->user()->id;
        $wasInvite = $member->status === 'invited';
        try {
            $this->registry->removeMember($startup, $request->user(), $member);
        } catch (RegistryException $exception) {
            return back()->withErrors(['team' => $exception->getMessage()]);
        }
        if ($leaving) {
            return redirect()->route('workspace.startups.index')->with('status', 'You left '.$startup->display_name.'.');
        }

        return back()->with('status', $wasInvite ? 'Invitation cancelled.' : 'Removed from the team.');
    }

    public function decide(Request $request, Startup $startup, StartupMembership $member): RedirectResponse
    {
        $this->membershipOr404($request, $startup);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'decline'])],
            'role' => ['nullable', Rule::in(array_keys(StartupMembership::ROLES))],
        ]);
        $approve = $data['decision'] === 'approve';

        return $this->attempt(fn () => $this->registry->decideRequest($startup, $request->user(), $member, $approve, $data['role'] ?? 'team_member'),
            $approve ? 'Request approved.' : 'Request declined.');
    }

    private function attempt(callable $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (RegistryException $exception) {
            return back()->withErrors(['team' => $exception->getMessage()]);
        }

        return back()->with('status', $success);
    }
}
