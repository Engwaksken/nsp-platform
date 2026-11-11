<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Audit;
use App\Support\Features;
use App\Support\SecurityAlert;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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
        $rules = ['name' => ['required', 'string', 'max:255']];
        $emailChanging = Str::lower(trim((string) $request->input('email', $user->email))) !== Str::lower($user->email);

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

        $changes = [];
        if ($data['name'] !== $user->name) {
            $changes['name'] = [$user->name, $data['name']];
            $user->name = $data['name'];
        }

        $oldEmail = $user->email;
        if ($emailChanging) {
            $changes['email'] = [$oldEmail, Str::lower($data['email'])];
            $user->email = Str::lower($data['email']);
            $user->email_verified_at = null;
        }

        if ($changes === []) {
            return back()->with('status', 'Nothing to change.');
        }

        $user->save();
        Audit::log('account.profile_updated', $user, $changes);

        if (! $emailChanging) {
            return back()->with('status', 'Your profile was saved.');
        }

        // Tell the old address (in case someone else did this) and confirm the new one.
        SecurityAlert::send(new User(['name' => $user->name, 'email' => $oldEmail]), 'Your email address was changed', 'The email address of your account was changed to '.$user->email);
        try {
            $user->sendEmailVerificationNotification();
        } catch (Throwable $exception) {
            Log::warning('[account] verification email failed', ['user_id' => $user->id, 'exception' => class_basename($exception)]);

            return back()->with('status', 'Your email address was changed, but the confirmation email could not be sent. Use "Resend confirmation" to try again.');
        }

        return back()->with('status', 'Your email address was changed. We sent a confirmation link to '.$user->email.'.');
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

        // Sign out every other session; keep this one.
        DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $request->session()->getId())->delete();
        $request->session()->regenerate();

        Audit::log('account.password_changed', $user);
        SecurityAlert::send($user, 'Your password was changed', 'The password of your account was changed');

        return back()->with('status', 'Your password was changed. Other devices have been signed out.');
    }

    private function canChangeCredentials(User $user): bool
    {
        return Features::enabled('local_login') && filled($user->password);
    }
}
