<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginAudit;
use App\Models\User;
use App\Support\Audit;
use App\Support\SecurityAlert;
use App\Support\Settings;
use App\Support\Totp;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Two-factor sign-in with an authenticator app (TOTP) and one-time recovery codes. */
class MfaController extends Controller
{
    use CompletesLogin;

    private const MAX_ATTEMPTS = 5;

    /** Second step of sign-in. */
    public function challenge(Request $request): View|RedirectResponse
    {
        if (! $this->pendingUser($request)) {
            return redirect()->route('login')->withErrors(['email' => 'Please sign in again.']);
        }

        return view('auth.mfa-challenge');
    }

    public function verify(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('login')->withErrors(['email' => 'Your sign-in expired. Please sign in again.']);
        }
        $data = $request->validate(['code' => ['required', 'string', 'max:32']]);

        $throttleKey = 'mfa|'.$user->id;
        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages(['code' => 'Too many attempts. Try again in '.RateLimiter::availableIn($throttleKey).' seconds.']);
        }

        $code = trim($data['code']);
        $usedRecovery = false;
        $step = Totp::verify($user->mfa_secret, $code, $user->mfa_last_step);
        if ($step !== null) {
            $user->forceFill(['mfa_last_step' => $step])->save();
        } elseif (strlen(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '') === 10 && $user->useRecoveryCode($code)) {
            $usedRecovery = true;
            Audit::log('auth.mfa_recovery_code_used', $user, ['codes_left' => $user->recoveryCodesLeft()]);
        } else {
            RateLimiter::hit($throttleKey, 300);
            LoginAudit::query()->create([
                'user_id' => $user->id, 'method' => 'mfa', 'success' => false, 'reason' => 'bad_code',
                'ip_hash' => Audit::hashIdentifier($request->ip()),
            ]);
            throw ValidationException::withMessages(['code' => 'That code is not valid. Check the time on your phone and try again.']);
        }

        RateLimiter::clear($throttleKey);
        $pending = $request->session()->pull('mfa_pending');
        $response = $this->completeLogin($user, $pending['method'], (bool) $pending['remember'], true, $pending['extras'] ?? []);
        if ($usedRecovery) {
            $response->with('status', 'You used a recovery code. '.$user->recoveryCodesLeft().' left. Create new codes in your security settings if you are running low.');
        }

        return $response;
    }

    /** Security page: start enrolment, or manage an existing setup. */
    public function setup(Request $request): View
    {
        $user = $request->user();
        if ($user->hasMfa()) {
            return view('auth.mfa-manage', ['user' => $user, 'recoveryCodes' => session('recovery_codes')]);
        }

        $secret = $request->session()->get('mfa_setup_secret') ?? Totp::generateSecret();
        $request->session()->put('mfa_setup_secret', $secret);
        $uri = Totp::provisioningUri($secret, $user->email, (string) Settings::get('site_name'));
        $writer = new Writer(new ImageRenderer(new RendererStyle(220, 2), new SvgImageBackEnd));

        return view('auth.mfa-setup', [
            'secret' => trim(chunk_split($secret, 4, ' ')),
            'qrSvg' => $writer->writeString($uri),
            'required' => $user->requiresMfa(),
        ]);
    }

    public function enable(Request $request): RedirectResponse
    {
        $user = $request->user();
        $secret = $request->session()->get('mfa_setup_secret');
        abort_if($user->hasMfa() || ! $secret, 409, 'Two-factor sign-in is already set up, or setup expired. Reload the page.');
        $data = $request->validate(['code' => ['required', 'string', 'max:10']]);

        $step = Totp::verify($secret, $data['code']);
        if ($step === null) {
            throw ValidationException::withMessages(['code' => 'That code is not valid. Scan the QR code again and enter the current 6-digit code.']);
        }

        $codes = $user->enableMfa($secret, $step);
        $request->session()->forget('mfa_setup_secret');
        $request->session()->put('mfa_verified', true);
        Audit::log('auth.mfa_enabled', $user);
        SecurityAlert::send($user, 'Two-factor sign-in was turned on', 'Two-factor sign-in was set up on your NSP account');

        return redirect()->route('mfa.setup')->with('recovery_codes', $codes)->with('status', 'Two-factor sign-in is on.');
    }

    public function regenerateCodes(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasMfa(), 404);
        $this->confirmPassword($request, $user);

        $codes = $user->regenerateRecoveryCodes();
        Audit::log('auth.mfa_recovery_codes_regenerated', $user);
        SecurityAlert::send($user, 'New recovery codes were created', 'New two-factor recovery codes were created for your NSP account');

        return redirect()->route('mfa.setup')->with('recovery_codes', $codes)->with('status', 'New recovery codes created. The old ones no longer work.');
    }

    public function disable(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasMfa(), 404);
        if ($user->requiresMfa()) {
            return back()->withErrors(['password' => 'Your role requires two-factor sign-in, so it cannot be turned off. To move it to a new phone, ask a super admin to reset it.']);
        }
        $this->confirmPassword($request, $user);

        $user->disableMfa();
        Audit::log('auth.mfa_disabled', $user);
        SecurityAlert::send($user, 'Two-factor sign-in was turned off', 'Two-factor sign-in was turned off on your NSP account');

        return redirect()->route('mfa.setup')->with('status', 'Two-factor sign-in is off.');
    }

    private function confirmPassword(Request $request, User $user): void
    {
        $request->validate(['password' => ['required', 'string']]);
        if (! $user->password || ! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages(['password' => 'The password is incorrect.']);
        }
    }

    private function pendingUser(Request $request): ?User
    {
        $pending = $request->session()->get('mfa_pending');
        if (! is_array($pending) || now()->timestamp - (int) $pending['at'] > self::MFA_PENDING_SECONDS) {
            $request->session()->forget('mfa_pending');

            return null;
        }
        $user = User::query()->find($pending['user_id']);

        return $user && $user->is_active && $user->hasMfa() ? $user : null;
    }
}
