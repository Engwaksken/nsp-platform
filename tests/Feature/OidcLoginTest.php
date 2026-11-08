<?php

namespace Tests\Feature;

use App\Models\LoginAudit;
use App\Models\User;
use App\Support\Features;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Runs the full authorization-code + PKCE flow against a fake identity
 * provider that signs ID tokens with a test-only RSA key (tests/Fixtures).
 */
class OidcLoginTest extends TestCase
{
    private const ISSUER = 'https://idp.test/realms/hive';

    private const CLIENT = 'nsp-web';

    private string $privateKey;

    /** The token endpoint returns whatever this holds. */
    private array $tokenResponse = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->privateKey = (string) file_get_contents(base_path('tests/Fixtures/oidc-test-key.pem'));
        config(['nsp.oidc' => array_merge(config('nsp.oidc'), [
            'issuer' => self::ISSUER,
            'client_id' => self::CLIENT,
            'client_secret' => 'test-secret',
            'redirect_uri' => 'http://localhost/auth/oidc/callback',
            'link_verified_email' => false,
        ])]);
        Features::set('oidc', true, null);
        Cache::flush();

        $details = openssl_pkey_get_details(openssl_pkey_get_private($this->privateKey));
        $jwk = [
            'kty' => 'RSA', 'kid' => 'key-1', 'use' => 'sig', 'alg' => 'RS256',
            'n' => rtrim(strtr(base64_encode($details['rsa']['n']), '+/', '-_'), '='),
            'e' => rtrim(strtr(base64_encode($details['rsa']['e']), '+/', '-_'), '='),
        ];

        Http::fake([
            'idp.test/realms/hive/.well-known/openid-configuration' => Http::response([
                'issuer' => self::ISSUER,
                'authorization_endpoint' => self::ISSUER.'/auth',
                'token_endpoint' => self::ISSUER.'/token',
                'jwks_uri' => self::ISSUER.'/certs',
                'end_session_endpoint' => self::ISSUER.'/logout',
            ]),
            'idp.test/realms/hive/certs' => Http::response(['keys' => [$jwk]]),
            'idp.test/realms/hive/token' => fn () => Http::response($this->tokenResponse),
        ]);
    }

    private function idToken(array $overrides = [], ?string $key = null, string $alg = 'RS256', string $kid = 'key-1'): string
    {
        $claims = array_merge([
            'iss' => self::ISSUER, 'aud' => self::CLIENT, 'sub' => 'subject-123',
            'iat' => time(), 'exp' => time() + 300, 'nonce' => 'nonce-abc',
            'email' => 'founder@startup.test', 'email_verified' => true, 'name' => 'Founder One',
        ], $overrides);

        return JWT::encode($claims, $key ?? $this->privateKey, $alg, $kid);
    }

    private function completeLogin(string $idToken, array $session = [], array $query = [])
    {
        $this->tokenResponse = ['id_token' => $idToken, 'access_token' => 'at', 'token_type' => 'Bearer'];

        return $this->withSession(['oidc_login' => array_merge([
            'state' => 'state-xyz', 'nonce' => 'nonce-abc', 'verifier' => 'verifier-123', 'started_at' => now()->timestamp,
        ], $session)])->get('/auth/oidc/callback?'.http_build_query(array_merge(['state' => 'state-xyz', 'code' => 'code-1'], $query)));
    }

    public function test_redirect_uses_pkce_s256_state_and_nonce(): void
    {
        $response = $this->get('/auth/oidc/redirect');

        $location = $response->headers->get('Location');
        $this->assertStringStartsWith(self::ISSUER.'/auth?', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $pending = session('oidc_login');

        $this->assertSame('code', $query['response_type']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertSame($pending['state'], $query['state']);
        $this->assertSame($pending['nonce'], $query['nonce']);
        $expectedChallenge = rtrim(strtr(base64_encode(hash('sha256', $pending['verifier'], true)), '+/', '-_'), '=');
        $this->assertSame($expectedChallenge, $query['code_challenge']);
    }

    public function test_successful_login_creates_a_startup_user_and_sends_the_verifier(): void
    {
        $this->completeLogin($this->idToken())->assertRedirect('/workspace');

        $user = User::query()->where('oidc_subject', 'subject-123')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(self::ISSUER, $user->oidc_issuer);
        $this->assertTrue($user->hasRole('startup_user'));
        $this->assertNull($user->password);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/token')
            && $request['code_verifier'] === 'verifier-123' && $request['grant_type'] === 'authorization_code');
        $this->assertTrue(LoginAudit::query()->where('method', 'oidc')->where('success', true)->exists());
    }

    public function test_returning_user_is_matched_by_subject_not_email(): void
    {
        $this->completeLogin($this->idToken())->assertRedirect();
        $this->post('/logout');

        $this->completeLogin($this->idToken(['email' => 'new-address@startup.test']))->assertRedirect('/workspace');
        $this->assertSame(1, User::query()->count());
    }

    public function test_state_mismatch_is_rejected(): void
    {
        $this->completeLogin($this->idToken(), [], ['state' => 'forged'])->assertRedirect('/login')->assertSessionHasErrors('oidc');
        $this->assertGuest();
    }

    public function test_missing_session_is_rejected(): void
    {
        $this->get('/auth/oidc/callback?state=x&code=y')->assertRedirect('/login')->assertSessionHasErrors('oidc');
        $this->assertGuest();
    }

    public function test_tokens_with_bad_claims_are_rejected(): void
    {
        $bad = [
            'wrong nonce' => $this->idToken(['nonce' => 'replayed']),
            'wrong audience' => $this->idToken(['aud' => 'another-app']),
            'wrong issuer' => $this->idToken(['iss' => 'https://evil.test']),
            'expired' => $this->idToken(['exp' => time() - 3600, 'iat' => time() - 7200]),
            'wrong key' => $this->idToken([], (string) file_get_contents(base_path('tests/Fixtures/oidc-other-key.pem'))),
            'symmetric alg' => $this->idToken([], 'a-shared-secret-that-is-long-enough-for-hs256', 'HS256'),
            'multi-aud without azp' => $this->idToken(['aud' => [self::CLIENT, 'other']]),
        ];

        foreach ($bad as $case => $token) {
            $this->completeLogin($token)->assertRedirect('/login');
            $this->assertGuest();
            $this->assertSame(0, User::query()->count(), "Accepted a token with {$case}");
        }
    }

    public function test_unsigned_token_is_rejected(): void
    {
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'none', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $body = rtrim(strtr(base64_encode(json_encode(['iss' => self::ISSUER, 'aud' => self::CLIENT, 'sub' => 'x', 'nonce' => 'nonce-abc', 'exp' => time() + 60, 'iat' => time(), 'email' => 'x@y.test'])), '+/', '-_'), '=');

        $this->completeLogin("{$header}.{$body}.")->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_existing_local_account_is_not_linked_by_email_by_default(): void
    {
        $local = User::factory()->create(['email' => 'founder@startup.test']);

        $this->completeLogin($this->idToken())->assertRedirect('/login')->assertSessionHasErrors('oidc');
        $this->assertGuest();
        $this->assertNull($local->fresh()->oidc_subject);
    }

    public function test_linking_by_verified_email_when_the_owner_enables_it(): void
    {
        config(['nsp.oidc.link_verified_email' => true]);
        $local = User::factory()->create(['email' => 'founder@startup.test']);

        $this->completeLogin($this->idToken(['email_verified' => false]))->assertRedirect('/login');
        $this->assertGuest();

        $this->completeLogin($this->idToken())->assertRedirect();
        $this->assertAuthenticatedAs($local->fresh());
        $this->assertSame('subject-123', $local->fresh()->oidc_subject);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.oidc_linked']);
    }

    public function test_deactivated_sso_user_cannot_sign_in(): void
    {
        $this->completeLogin($this->idToken());
        $this->post('/logout');
        User::query()->update(['is_active' => false]);

        $this->completeLogin($this->idToken())->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_logout_goes_to_the_identity_provider(): void
    {
        $this->completeLogin($this->idToken());

        $location = $this->post('/logout')->headers->get('Location');
        $this->assertStringStartsWith(self::ISSUER.'/logout?', $location);
        $this->assertStringContainsString('id_token_hint=', $location);
        $this->assertGuest();
    }

    public function test_sso_routes_are_hidden_when_the_feature_is_off(): void
    {
        Features::set('oidc', false, null);

        $this->get('/auth/oidc/redirect')->assertNotFound();
        $this->get('/login')->assertDontSee('Sign in with your Hive account');
    }

    public function test_unverified_email_does_not_create_an_account(): void
    {
        $this->completeLogin($this->idToken(['email_verified' => false]))->assertRedirect('/login');
        $this->assertGuest();
        $this->assertFalse(User::query()->where('email', 'founder@startup.test')->exists());
    }
}
