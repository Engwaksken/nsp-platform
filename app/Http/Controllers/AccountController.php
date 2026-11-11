<?php

namespace App\Http\Controllers;

use App\Mail\ServiceNotice;
use App\Models\User;
use App\Support\Audit;
use App\Support\Features;
use App\Support\SecurityAlert;
use App\Support\ThemePalettes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Throwable;

/**
 * "My profile": a member changes their own name, email address and password.
 * Email and password changes need the current password, so they are only possible for
 * accounts that have one (single sign-on accounts are managed by their sign-in provider).
 */
class AccountController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('account.edit', [
            'user' => $user,
            'canChangeCredentials' => $this->canChangeCredentials($user),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();
        // Compare and store addresses in lower case, so the unique check matches what is saved.
        $request->merge(['email' => Str::lower(trim((string) $request->input('email', $user->email)))]);
        $emailChanging = $request->input('email') !== Str::lower($user->email);

        $rules = ['name' => ['required', 'string', 'max:255']];
        if ($emailChanging) {
            abort_unless($this->canChangeCredentials($user), 403);
            $rules['email'] = ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)];
            $rules['email_current_password'] = ['required', 'string'];
        }

        $data = $request->validate($rules);

        if ($emailChanging && ! Hash::check($data['email_current_password'], $user->password)) {
            return back()->withInput($request->except('email_current_password'))
                ->withErrors(['email_current_password' => 'That is not your current password.']);
        }

        if ($data['name'] !== $user->name) {
            Audit::log('account.profile_updated', $user, ['name' => [$user->name, $data['name']]]);
            $user->forceFill(['name' => $data['name']])->save();
        }

        if (! $emailChanging) {
            return back()->with('status', 'Your profile was saved.');
        }

        // The address only changes once its owner confirms it from that inbox (see confirmEmail).
        $user->forceFill(['pending_email' => $data['email']])->save();
        Audit::log('account.email_change_requested', $user, ['pending_email' => $data['email']]);

        return $this->sendEmailConfirmation($user)
            ? back()->with('status', 'Almost done: we sent a link to '.$data['email'].'. Your email address changes when you open it.')
            : back()->withErrors(['email' => 'The confirmation email could not be sent. Please try again later.']);
    }

    /** Signed link from the new inbox: apply the pending address. */
    public function confirmEmail(Request $request, string $hash): RedirectResponse
    {
        $user = $request->user();
        $pending = (string) $user->pending_email;
        if ($pending === '' || ! hash_equals(sha1($pending), $hash)) {
            return redirect()->route('account.edit')->withErrors(['email' => 'This confirmation link is no longer valid. Request the change again.']);
        }
        if (User::query()->where('email', $pending)->whereKeyNot($user->id)->exists()) {
            $user->forceFill(['pending_email' => null])->save();

            return redirect()->route('account.edit')->withErrors(['email' => 'Another account now uses that email address.']);
        }

        $old = $user->email;
        $user->forceFill(['email' => $pending, 'pending_email' => null, 'email_verified_at' => now()])->save();
        Audit::log('account.email_changed', $user, ['email' => [$old, $pending]]);
        SecurityAlert::send(new User(['name' => $user->name, 'email' => $old]), 'Your email address was changed', 'The email address of your account was changed to '.$pending);

        return redirect()->route('account.edit')->with('status', 'Your email address is now '.$pending.'.');
    }

    public function cancelEmail(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['pending_email' => null])->save();

        return back()->with('status', 'The email change was cancelled.');
    }

    /** The member's own colour palette (App\Support\ThemePalettes); "brand" goes back to the site colours. */
    public function updateAppearance(Request $request): RedirectResponse
    {
        $data = $request->validate(['theme_palette' => ['required', Rule::in(ThemePalettes::keys())]]);
        $palette = $data['theme_palette'] === ThemePalettes::DEFAULT ? null : $data['theme_palette'];
        $request->user()->forceFill(['theme_palette' => $palette])->save();

        return redirect()->to(route('account.edit').'#appearance')->with('status', 'Your colours were saved.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canChangeCredentials($user), 403);

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->numbers()],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'That is not your current password.']);
        }

        $user->forceFill(['password' => $data['password'], 'remember_token' => Str::random(60)])->save();

        // Sign out every other session; keep this one. Signing in again gives this browser a new
        // session id and, if it used "remember me", a cookie with the new remember token.
        $remember = $request->hasCookie(Auth::guard()->getRecallerName());
        DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $request->session()->getId())->delete();
        Auth::guard()->login($user, $remember);

        Audit::log('account.password_changed', $user);
        SecurityAlert::send($user, 'Your password was changed', 'The password of your account was changed');

        return back()->with('status', 'Your password was changed. Other devices have been signed out.');
    }

    private function sendEmailConfirmation(User $user): bool
    {
        $url = URL::temporarySignedRoute('account.email.confirm', now()->addHour(), ['hash' => sha1((string) $user->pending_email)]);
        try {
            Mail::to($user->pending_email)->send(new ServiceNotice('Confirm your new email address', [
                'Someone asked to use this address for the '.config('app.name').' account of '.$user->name.'.',
                'If that was you, open the link below while signed in. It works for 60 minutes.',
                'If it was not you, ignore this email: nothing changes.',
            ], $url));
        } catch (Throwable $exception) {
            Log::warning('[account] email change confirmation failed', ['user_id' => $user->id, 'exception' => class_basename($exception)]);

            return false;
        }

        return true;
    }

    private function canChangeCredentials(User $user): bool
    {
        return Features::enabled('local_login') && filled($user->password);
    }
}
