<?php

namespace App\Http\Controllers\Auth;

use App\Models\LoginAudit;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * The last step of every sign-in (password, single sign-on, or two-factor
 * challenge). Users who must use two-factor sign-in are not logged in until
 * they pass it, unless the identity provider already checked a second factor.
 */
trait CompletesLogin
{
    use RedirectsAfterLogin;

    /** How long a password-verified sign-in may wait for its two-factor code. */
    protected const MFA_PENDING_SECONDS = 300;

    protected function completeLogin(User $user, string $method, bool $remember = false, bool $secondFactorDone = false, array $sessionExtras = [], ?string $identifier = null): RedirectResponse
    {
        if ($user->hasMfa() && ! $secondFactorDone) {
            session()->regenerate();
            session()->put('mfa_pending', [
                'user_id' => $user->id,
                'remember' => $remember,
                'method' => $method,
                'extras' => $sessionExtras,
                'at' => now()->timestamp,
            ]);

            return redirect()->route('mfa.challenge');
        }

        Auth::login($user, $remember);
        session()->regenerate();
        foreach ($sessionExtras as $key => $value) {
            session()->put($key, $value);
        }
        session()->put('mfa_verified', $secondFactorDone);
        if (! $secondFactorDone && ! $user->hasMfa()) {
            session()->forget('mfa_verified');
        }
        $user->forceFill(['last_login_at' => now()])->save();

        LoginAudit::query()->create([
            'user_id' => $user->id,
            'method' => $method,
            'success' => true,
            'reason' => $secondFactorDone ? 'mfa' : null,
            'identifier_hash' => Audit::hashIdentifier($identifier),
            'ip_hash' => Audit::hashIdentifier(request()->ip()),
        ]);

        // Admins without two-factor sign-in are sent to set it up before they can use the admin area.
        if ($user->requiresMfa() && ! $user->hasMfa()) {
            return redirect()->route('mfa.setup')->with('status', 'Set up two-factor sign-in to use the admin area.');
        }

        return $this->redirectAfterLogin($user);
    }
}
