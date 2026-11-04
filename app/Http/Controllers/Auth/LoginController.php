<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginAudit;
use App\Models\User;
use App\Services\Oidc\OidcClient;
use App\Support\Audit;
use App\Support\Features;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    use CompletesLogin;

    private const MAX_ATTEMPTS = 5;

    public function show(): View
    {
        return view('auth.login', [
            'localLogin' => Features::enabled('local_login'),
            'oidcLogin' => Features::enabled('oidc') && OidcClient::fromConfig()->isConfigured(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(Features::enabled('local_login'), 404);

        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);
        $throttleKey = Str::lower($credentials['email']).'|'.$request->ip();
        $ipKey = 'login-ip|'.$request->ip();
        $accountKey = 'login-account|'.Str::lower($credentials['email']);

        foreach ([[$throttleKey, self::MAX_ATTEMPTS], [$ipKey, 30], [$accountKey, 20]] as [$key, $max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $this->audit(null, false, 'throttled', $credentials['email']);
                throw ValidationException::withMessages([
                    'email' => 'Too many sign-in attempts. Try again in '.max(1, RateLimiter::availableIn($key)).' seconds.',
                ]);
            }
        }

        $user = User::query()->where('email', Str::lower($credentials['email']))->first();
        // Hash::check runs even when the user is missing so response time does not reveal which emails exist.
        $passwordOk = Hash::check($credentials['password'], $user?->password ?? '$2y$12$'.str_repeat('a', 53));

        if (! $user || ! $user->password || ! $passwordOk) {
            RateLimiter::hit($throttleKey, 60);
            RateLimiter::hit($ipKey, 600);
            RateLimiter::hit($accountKey, 900);
            $this->audit($user, false, 'bad_credentials', $credentials['email']);
            throw ValidationException::withMessages(['email' => 'The email or password is incorrect.']);
        }
        if (! $user->is_active) {
            $this->audit($user, false, 'inactive', $credentials['email']);
            throw ValidationException::withMessages(['email' => 'Your account is not active. Contact an administrator.']);
        }

        RateLimiter::clear($throttleKey);

        return $this->completeLogin($user, 'local', $request->boolean('remember'), identifier: $credentials['email']);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $idToken = $request->session()->get('oidc_id_token');
        if ($request->user()) {
            Audit::log('auth.logout', $request->user());
        }
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($idToken && Features::enabled('oidc')) {
            $endSession = OidcClient::fromConfig()->endSessionUrl($idToken);
            if ($endSession) {
                return redirect()->away($endSession);
            }
        }

        return redirect()->route('home');
    }

    private function audit(?User $user, bool $success, ?string $reason, string $identifier): void
    {
        LoginAudit::query()->create([
            'user_id' => $user?->id,
            'method' => 'local',
            'success' => $success,
            'reason' => $reason,
            'identifier_hash' => Audit::hashIdentifier($identifier),
            'ip_hash' => Audit::hashIdentifier(request()->ip()),
        ]);
    }
}
