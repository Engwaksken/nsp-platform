<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit;
use App\Support\Features;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Throwable;

/**
 * Password reset for local accounts. People who sign in only with Hive single
 * sign-on reset their password at the identity provider instead.
 */
class PasswordResetController extends Controller
{
    private const GENERIC_MESSAGE = 'If an account with a password uses that email, we have sent a reset link. It works for 60 minutes.';

    public function request(): View
    {
        abort_unless(Features::enabled('local_login'), 404);

        return view('auth.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        abort_unless(Features::enabled('local_login'), 404);
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $email = Str::lower($data['email']);

        // The same answer whether or not the account exists, so the form cannot be used to find accounts.
        $user = User::query()->where('email', $email)->first();
        if ($user && $user->is_active && $user->password) {
            try {
                $status = Password::broker()->sendResetLink(['email' => $email]);
                if ($status === Password::RESET_LINK_SENT) {
                    Audit::log('auth.password_reset_requested', $user);
                }
            } catch (Throwable $exception) {
                Log::error('[password-reset] mail failed: '.$exception->getMessage());
            }
        }

        return back()->with('status', self::GENERIC_MESSAGE);
    }

    public function edit(Request $request, string $token): View
    {
        abort_unless(Features::enabled('local_login'), 404);

        return view('auth.reset-password', ['token' => $token, 'email' => (string) $request->query('email', '')]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless(Features::enabled('local_login'), 404);
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->numbers()],
        ]);
        $data['email'] = Str::lower($data['email']);

        $status = Password::broker()->reset($data, function (User $user, string $password) {
            abort_unless($user->is_active && $user->password, 403);
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            // Sign the account out everywhere: an attacker's session must not survive a reset.
            DB::table('sessions')->where('user_id', $user->id)->delete();
            Audit::log('auth.password_reset', $user);
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'This reset link is invalid or has expired. Request a new one.']);
        }

        return redirect()->route('login')->with('status', 'Your password was changed. Sign in with the new password.');
    }
}
