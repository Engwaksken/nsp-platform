<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Registry\ActiveStartup;
use App\Registry\RegistryException;
use App\Registry\RegistryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvitationController extends Controller
{
    public function __construct(private readonly RegistryService $registry) {}

    public function show(Request $request, string $token): View
    {
        $invite = $this->registry->findInvitation($token);
        $valid = $invite && ! $invite->invite_expires_at?->isPast() && $invite->startup?->status === 'active';
        $emailMatches = $valid && strcasecmp((string) $invite->invited_email, (string) $request->user()->email) === 0;

        return view('workspace.invitation', compact('invite', 'valid', 'emailMatches', 'token'));
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        try {
            $membership = $this->registry->acceptInvitation($token, $request->user());
        } catch (RegistryException $exception) {
            return redirect()->route('workspace.startups.index')->withErrors(['invite' => $exception->getMessage()]);
        }
        ActiveStartup::switchTo($membership->startup);

        return redirect()->route('workspace.startups.show', $membership->startup)->with('status', 'Welcome to '.$membership->startup->display_name.'.');
    }

    public function decline(Request $request, string $token): RedirectResponse
    {
        $this->registry->declineInvitation($token, $request->user());

        return redirect()->route('workspace.startups.index')->with('status', 'Invitation declined.');
    }
}
