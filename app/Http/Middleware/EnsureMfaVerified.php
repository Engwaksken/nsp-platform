<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Admin area guard: users whose role needs two-factor sign-in must have set it up and passed it this session. */
class EnsureMfaVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->requiresMfa() || $request->session()->get('mfa_verified') === true) {
            return $next($request);
        }

        if (! $user->hasMfa()) {
            return redirect()->route('mfa.setup')->with('status', 'Set up two-factor sign-in to use the admin area.');
        }

        // Signed in without the second factor (for example a role was added mid-session): sign in again.
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['email' => 'Please sign in again with your two-factor code.']);
    }
}
