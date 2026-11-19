<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\AccountAlreadyExists;
use App\Models\Registry\StartupMembership;
use App\Models\User;
use App\Support\Audit;
use App\Support\Features;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Throwable;

/**
 * Self-service sign-up while NSP runs on local login (decision D1 deferred).
 *
 * Everyone gets the same basic account; the "what brings you here" answer only decides
 * where they land after confirming their email. Investor and expert roles still come
 * from staff verification of the profile they fill in.
 *
 * The answer never reveals whether an email already has an account: every sign-up
 * ends on the same "check your email" page. A new account gets a confirmation link;
 * an existing one gets a note pointing to sign-in and password reset. The person
 * signs in after confirming, so nobody can act as an address they do not own.
 */
class RegisterController extends Controller
{
    /** purpose => [label, hint, feature flag, landing route] */
    public const PURPOSES = [
        'startup' => ['I run or work at a startup', 'Register your startup or join your team.', 'registry', 'workspace.startups.create'],
        'investor' => ['I invest in startups', 'Tell us about your fund. Hive staff verify investors before they see startups.', 'funding', 'investor.apply'],
        'expert' => ['I mentor or advise startups', 'Create your expert profile. Hive staff verify experts before they are matched.', 'experts', 'experts.profile'],
        'talent' => ['I am looking for a job', 'Build a candidate profile and apply to startup jobs.', 'talent', 'talent.profile'],
    ];

    public static function available(): bool
    {
        return Features::enabled('local_login') && Features::enabled('startup_signup');
    }

    /** @return array<string, array{0: string, 1: string, 2: string, 3: string}> purposes whose module is switched on */
    public static function purposes(): array
    {
        return array_filter(self::PURPOSES, fn ($purpose) => Features::enabled($purpose[2]));
    }

    /**
     * Where a newly confirmed person should go first, or null for the usual dashboard.
     * Founders who already have a startup (or a pending join request) skip the form.
     */
    public static function landingFor(User $user): ?string
    {
        if ($user->canUseAdmin()) {
            return null;
        }
        $purpose = $user->signup_purpose ?? ($user->hasRole('startup_user') ? 'startup' : null);
        $definition = self::purposes()[$purpose] ?? null;
        if (! $definition) {
            return null;
        }
        if ($purpose === 'startup' && StartupMembership::query()->where('user_id', $user->id)->whereIn('status', ['active', 'requested'])->exists()) {
            return null;
        }

        return route($definition[3]);
    }

    public function show(Request $request): View
    {
        abort_unless(self::available(), 404);
        $purposes = self::purposes();
        $selected = $request->query('as');

        return view('auth.register', [
            'purposes' => $purposes,
            'selected' => is_string($selected) && isset($purposes[$selected]) ? $selected : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(self::available(), 404);

        $purposes = self::purposes();
        $data = $request->validate([
            'purpose' => [$purposes ? 'required' : 'nullable', Rule::in(array_keys($purposes))],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->numbers()],
        ], ['purpose.required' => 'Choose what brings you to NSP.']);
        $email = Str::lower(trim($data['email']));

        $existing = User::query()->where('email', $email)->first();
        if ($existing) {
            $this->tellExistingOwner($existing);
        } else {
            $user = DB::transaction(function () use ($data, $email) {
                $user = new User;
                $user->forceFill([
                    'name' => trim($data['name']),
                    'email' => $email,
                    'password' => $data['password'],
                    'is_active' => true,
                    'signup_purpose' => $data['purpose'] ?? null,
                ])->save();
                // The basic member role; investor and expert roles come only from staff verification.
                $user->assignRole('startup_user');
                Audit::log('auth.self_registered', $user, ['purpose' => $data['purpose'] ?? null]);

                return $user;
            });
            $this->sendConfirmation($user);
        }

        $request->session()->put('signup_email', $email);

        return redirect()->route('register.sent');
    }

    public function sent(Request $request): View|RedirectResponse
    {
        $email = $request->session()->get('signup_email');
        if (! is_string($email)) {
            return redirect()->route('register');
        }

        return view('auth.register-sent', ['email' => $email]);
    }

    private function sendConfirmation(User $user): void
    {
        try {
            $user->sendEmailVerificationNotification();
        } catch (Throwable $exception) {
            // The account exists; the person can ask for a new link after signing in.
            Log::warning('[signup] confirmation email failed', ['user_id' => $user->id, 'exception' => class_basename($exception)]);
        }
    }

    private function tellExistingOwner(User $user): void
    {
        if (! $user->is_active) {
            return;
        }
        try {
            Mail::to($user->email)->queue(new AccountAlreadyExists($user->password === null));
        } catch (Throwable $exception) {
            Log::warning('[signup] existing-account notice failed', ['user_id' => $user->id, 'exception' => class_basename($exception)]);
        }
        Audit::log('auth.signup_existing_email', $user);
    }
}
