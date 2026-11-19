<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class EmailVerificationController extends Controller
{
    public function notice(): View|RedirectResponse
    {
        if (request()->user()->hasVerifiedEmail()) {
            return redirect()->route('home');
        }

        return view('auth.verify-email');
    }

    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::query()->findOrFail($id);
        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        if ($user->hasVerifiedEmail()) {
            return $this->next($request, $user, 'Your email address is already confirmed.');
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
            Audit::log('auth.email_verified', $user);
        }

        return $this->next($request, $user, 'Your email address has been confirmed.');
    }

    /**
     * New members go to the page for what they signed up for (startup form, investor
     * or expert profile, candidate profile); signed-out people sign in first.
     * The link alone never signs anyone in.
     */
    private function next(Request $request, User $user, string $status): RedirectResponse
    {
        $target = RegisterController::landingFor($user);
        if ($user->signup_purpose !== null) {
            // Only the first landing is steered; afterwards the usual dashboard applies.
            $user->forceFill(['signup_purpose' => null])->save();
        }

        if (! $request->user()) {
            if ($target) {
                $request->session()->put('url.intended', $target);
            }

            return redirect()->route('login')->with('status', $status.' Sign in to continue.');
        }
        if ($request->user()->is($user) && $target) {
            return redirect()->to($target)->with('status', $status.' Next, complete your profile.');
        }

        return redirect()->route('home')->with('status', $status);
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user->hasVerifiedEmail()) {
            return redirect()->route('home')->with('status', 'Your email address is already confirmed.');
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (Throwable $exception) {
            Log::warning('[email-verification] resend failed', ['user_id' => $user->id, 'exception' => class_basename($exception)]);

            return back()->withErrors(['email' => 'The confirmation email could not be sent. Please try again later or contact support.']);
        }
        Audit::log('auth.email_verification_resent', $user);

        return back()->with('status', 'A new confirmation link has been sent.');
    }
}
