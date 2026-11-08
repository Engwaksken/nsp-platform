<?php

namespace App\Services\Oidc;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * OpenID Connect relying party: authorization code flow with PKCE (S256).
 * ID tokens are verified against the issuer's JWKS; iss, aud, azp, exp, iat
 * and nonce are all checked. Only asymmetric algorithms are accepted.
 */
class OidcClient
{
    private const ALLOWED_ALGORITHMS = ['RS256', 'RS384', 'RS512', 'ES256', 'ES384', 'PS256'];

    public function __construct(private readonly array $config) {}

    public static function fromConfig(): self
    {
        return new self(config('nsp.oidc'));
    }

    public function isConfigured(): bool
    {
        return filled($this->config['issuer'] ?? null)
            && filled($this->config['client_id'] ?? null)
            && filled($this->config['redirect_uri'] ?? null);
    }

    public function issuer(): string
    {
        return rtrim((string) $this->config['issuer'], '/');
    }

    /** @return array<string, mixed> */
    public function discovery(): array
    {
        return Cache::remember('oidc.discovery.'.sha1($this->issuer()), $this->config['cache_seconds'] ?? 3600, function () {
            $document = $this->getJson($this->issuer().'/.well-known/openid-configuration');
            if (rtrim((string) ($document['issuer'] ?? ''), '/') !== $this->issuer()) {
                throw new OidcException('The identity provider reported a different issuer.');
            }
            foreach (['authorization_endpoint', 'token_endpoint', 'jwks_uri'] as $key) {
                if (empty($document[$key])) {
                    throw new OidcException("The identity provider discovery document has no {$key}.");
                }
                $this->assertSecureUrl((string) $document[$key]);
            }

            return $document;
        });
    }

    /**
     * Start a login. The caller must store state, nonce and verifier in the session.
     *
     * @return array{url: string, state: string, nonce: string, verifier: string}
     */
    public function authorizationRequest(): array
    {
        $state = Str::random(40);
        $nonce = Str::random(40);
        $verifier = self::base64Url(random_bytes(48));
        $challenge = self::base64Url(hash('sha256', $verifier, true));

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $this->config['client_id'],
            'redirect_uri' => $this->config['redirect_uri'],
            'scope' => $this->config['scopes'] ?? 'openid profile email',
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);

        $endpoint = (string) $this->discovery()['authorization_endpoint'];

        return [
            'url' => $endpoint.(str_contains($endpoint, '?') ? '&' : '?').$query,
            'state' => $state,
            'nonce' => $nonce,
            'verifier' => $verifier,
        ];
    }

    /** Exchange the authorization code; returns the raw token response. */
    public function exchangeCode(string $code, string $verifier): array
    {
        $form = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->config['redirect_uri'],
            'client_id' => $this->config['client_id'],
            'code_verifier' => $verifier,
        ];
        if (filled($this->config['client_secret'] ?? null)) {
            $form['client_secret'] = $this->config['client_secret'];
        }

        try {
            $response = Http::asForm()->acceptJson()->timeout(10)->post((string) $this->discovery()['token_endpoint'], $form);
        } catch (ConnectionException) {
            throw new OidcException('The identity provider could not be reached.');
        }
        if (! $response->successful() || ! is_string($response->json('id_token'))) {
            throw new OidcException('The identity provider did not return a valid token.');
        }

        return $response->json();
    }

    /**
     * Verify an ID token and return its claims.
     *
     * @return array<string, mixed>
     */
    public function validateIdToken(string $idToken, string $expectedNonce): array
    {
        JWT::$leeway = (int) ($this->config['leeway_seconds'] ?? 60);
        $header = json_decode((string) JWT::urlsafeB64Decode(explode('.', $idToken)[0] ?? ''), true);
        $algorithm = is_array($header) ? (string) ($header['alg'] ?? '') : '';
        if (! in_array($algorithm, self::ALLOWED_ALGORITHMS, true)) {
            throw new OidcException('The ID token uses an algorithm that is not allowed.');
        }

        try {
            $claims = (array) JWT::decode($idToken, $this->keys(false));
        } catch (\UnexpectedValueException $exception) {
            // The IdP may have rotated keys: refresh the key set once and retry.
            if (! str_contains($exception->getMessage(), '"kid"')) {
                throw new OidcException('The ID token could not be verified.', previous: $exception);
            }
            try {
                $claims = (array) JWT::decode($idToken, $this->keys(true));
            } catch (Throwable $retry) {
                throw new OidcException('The ID token could not be verified.', previous: $retry);
            }
        } catch (Throwable $exception) {
            throw new OidcException('The ID token could not be verified.', previous: $exception);
        }

        if (rtrim((string) ($claims['iss'] ?? ''), '/') !== $this->issuer()) {
            throw new OidcException('The ID token was issued by an unexpected issuer.');
        }
        $audiences = (array) ($claims['aud'] ?? []);
        if (! in_array($this->config['client_id'], $audiences, true)) {
            throw new OidcException('The ID token was not issued for this application.');
        }
        if (count($audiences) > 1 && ($claims['azp'] ?? null) !== $this->config['client_id']) {
            throw new OidcException('The ID token has an unexpected authorized party.');
        }
        if (! isset($claims['exp'], $claims['iat']) || empty($claims['sub'])) {
            throw new OidcException('The ID token is missing required claims.');
        }
        if (! is_string($claims['nonce'] ?? null) || ! hash_equals($expectedNonce, $claims['nonce'])) {
            throw new OidcException('The ID token nonce does not match this login.');
        }

        return $claims;
    }

    /** RP-initiated logout URL, or null if the IdP does not support it. */
    public function endSessionUrl(?string $idTokenHint): ?string
    {
        try {
            $endpoint = $this->discovery()['end_session_endpoint'] ?? null;
        } catch (Throwable) {
            return null;
        }
        if (! $endpoint) {
            return null;
        }
        $query = array_filter([
            'client_id' => $this->config['client_id'],
            'id_token_hint' => $idTokenHint,
            'post_logout_redirect_uri' => $this->config['post_logout_redirect_uri'] ?? null,
        ]);

        return $endpoint.(str_contains($endpoint, '?') ? '&' : '?').http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    private function keys(bool $refresh): array
    {
        $cacheKey = 'oidc.jwks.'.sha1($this->issuer());
        if ($refresh) {
            Cache::forget($cacheKey);
        }
        $jwks = Cache::remember($cacheKey, $this->config['cache_seconds'] ?? 3600,
            fn () => $this->getJson((string) $this->discovery()['jwks_uri']));

        return JWK::parseKeySet($jwks, 'RS256');
    }

    private function getJson(string $url): array
    {
        $this->assertSecureUrl($url);
        try {
            $response = Http::acceptJson()->timeout(10)->get($url);
        } catch (ConnectionException) {
            throw new OidcException('The identity provider could not be reached.');
        }
        if (! $response->successful() || ! is_array($response->json())) {
            throw new OidcException('The identity provider returned an invalid response.');
        }

        return $response->json();
    }

    private function assertSecureUrl(string $url): void
    {
        if (! str_starts_with($url, 'https://') && ! app()->environment(['local', 'testing'])) {
            throw new OidcException('Identity provider URLs must use HTTPS.');
        }
    }

    public static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
