<?php

namespace App\Http\Middleware;

use App\Models\Integration\ApiClient;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com",
            "img-src 'self' data: https:",
            "connect-src 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self' ".$this->formTargets($request),
            "object-src 'none'",
        ]);

        $response->headers->set('Content-Security-Policy', trim($csp));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    /**
     * Browsers apply form-action to redirects that follow a form post. Allow the
     * identity provider (sign-out) and, on the connect page only, the registered
     * redirect addresses of connected systems.
     */
    private function formTargets(Request $request): string
    {
        $origins = [self::origin((string) config('nsp.oidc.issuer'))];
        if ($request->is('connect/*')) {
            $origins = array_merge($origins, Cache::remember('nsp.connect_origins', 300, function () {
                if (! Schema::hasTable('api_clients')) {
                    return [];
                }

                return ApiClient::query()->where('is_active', true)->pluck('redirect_uris')
                    ->flatten()->map(fn ($uri) => self::origin((string) $uri))->filter()->unique()->values()->all();
            }));
        }

        return implode(' ', array_unique(array_filter($origins)));
    }

    private static function origin(string $url): string
    {
        $parts = parse_url($url);
        if (! isset($parts['scheme'], $parts['host']) || ! in_array($parts['scheme'], ['https', 'http'], true)) {
            return '';
        }

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
