<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginAudit;
use App\Models\User;
use App\Services\Oidc\OidcClient;
use App\Services\Oidc\OidcException;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Sign-in through the shared Hive identity provider (authorization code + PKCE). */
class OidcController extends Controller
{
    use CompletesLogin;

    public function __construct(private readonly OidcClient $client) {}

    public function redirect(Request $request): RedirectResponse
    {
        abort_unless($this->client->isConfigured(), 404);

        try {
            $authorization = $this->client->authorizationRequest();
        } catch (OidcException $exception) {
            Log::warning('[oidc] '.$exception->getMessage());

            return redirect()->route('login')->withErrors(['oidc' => 'Single sign-on is not available right now. Try again later.']);
        }

        $request->session()->put('oidc_login', [
            'state' => $authorization['state'],
            'nonce' => $authorization['nonce'],
            'verifier' => $authorization['verifier'],
            'started_at' => now()->timestamp,
        ]);

        return redirect()->away($authorization['url']);
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless($this->client->isConfigured(), 404);
        $pending = $request->session()->pull('oidc_login');

        if ($request->filled('error')) {
            return $this->fail(null, 'idp_error', 'Sign-in was cancelled or refused by the identity provider.');
        }
        if (! is_array($pending) || ! is_string($request->query('state'))
            || ! hash_equals($pending['state'], $request->query('state'))) {
            return $this->fail(null, 'bad_state', 'This sign-in link is invalid or has expired. Please start again.');
        }
        if (now()->timestamp - (int) $pending['started_at'] > 600) {
            return $this->fail(null, 'expired', 'This sign-in took too long. Please start again.');
        }
        if (! is_string($request->query('code')) || $request->query('code') === '') {
            return $this->fail(null, 'no_code', 'The identity provider did not return a sign-in code.');
        }

        try {
            $tokens = $this->client->exchangeCode($request->query('code'), $pending['verifier']);
            $claims = $this->client->validateIdToken($tokens['id_token'], $pending['nonce']);
        } catch (OidcException $exception) {
            Log::warning('[oidc] '.$exception->getMessage(), ['previous' => $exception->getPrevious()?->getMessage()]);

            return $this->fail(null, 'invalid_token', 'We could not verify your sign-in. Please try again.');
        }

        $result = $this->resolveUser($claims);
        if (is_string($result)) {
            return $this->fail(null, 'account_conflict', $result);
        }
        if (! $result->is_active) {
            return $this->fail($result, 'inactive', 'Your account is not active. Contact an administrator.');
        }

        // Trust the identity provider's second factor only when it says it used one (RFC 8176 "amr").
        $idpUsedMfa = (bool) array_intersect((array) ($claims['amr'] ?? []), ['mfa', 'otp', 'hwk', 'sms']);

        return $this->completeLogin($result, 'oidc', false, $idpUsedMfa, ['oidc_id_token' => $tokens['id_token']]);
    }

    /** Returns the user, or an error message when the identity cannot be safely matched. */
    private function resolveUser(array $claims): User|string
    {
        $issuer = $this->client->issuer();
        $subject = (string) $claims['sub'];

        $user = User::query()->where('oidc_issuer', $issuer)->where('oidc_subject', $subject)->first();
        if ($user) {
            return $user;
        }

        $email = Str::lower((string) ($claims['email'] ?? ''));
        $emailVerified = ($claims['email_verified'] ?? false) === true;
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Your identity provider account has no email address. Add one, then try again.';
        }

        $existing = User::query()->where('email', $email)->first();
        if ($existing) {
            // Never merge accounts on email alone unless the owner enabled it and the IdP verified the address.
            if (! config('nsp.oidc.link_verified_email') || ! $emailVerified || $existing->oidc_subject !== null) {
                return 'An NSP account already uses this email. Ask an administrator to link it to your Hive login.';
            }
            $existing->forceFill(['oidc_issuer' => $issuer, 'oidc_subject' => $subject])->save();
            Audit::log('auth.oidc_linked', $existing, ['issuer' => $issuer]);

            return $existing;
        }

        return DB::transaction(function () use ($claims, $email, $emailVerified, $issuer, $subject) {
            $user = new User;
            $user->forceFill([
                'name' => mb_substr((string) ($claims['name'] ?? $claims['preferred_username'] ?? Str::before($email, '@')), 0, 255),
                'email' => $email,
                'email_verified_at' => $emailVerified ? now() : null,
                'oidc_issuer' => $issuer,
                'oidc_subject' => $subject,
                'is_active' => true,
            ])->save();
            $user->assignRole('startup_user');
            Audit::log('auth.oidc_registered', $user);

            return $user;
        });
    }

    private function fail(?User $user, string $reason, string $message): RedirectResponse
    {
        $this->audit($user, false, $reason);

        return redirect()->route('login')->withErrors(['oidc' => $message]);
    }

    private function audit(?User $user, bool $success, ?string $reason): void
    {
        LoginAudit::query()->create([
            'user_id' => $user?->id,
            'method' => 'oidc',
            'success' => $success,
            'reason' => $reason,
            'ip_hash' => Audit::hashIdentifier(request()->ip()),
        ]);
    }
}
