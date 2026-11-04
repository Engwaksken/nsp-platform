<?php

use App\Http\Middleware\AuthenticateApiClient;
use App\Http\Middleware\EnsureAdminAccess;
use App\Http\Middleware\EnsureFeatureEnabled;
use App\Http\Middleware\EnsureMfaVerified;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleSeoRedirects;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global so they also apply to 404s for paths no route matches.
        $middleware->append(SecurityHeaders::class);
        // Reject requests for other hosts, so links built from the request (password reset) cannot point elsewhere.
        $middleware->trustHosts();
        $middleware->append(HandleSeoRedirects::class);
        $middleware->appendToGroup('web', EnsureUserIsActive::class);
        $middleware->alias([
            'admin' => EnsureAdminAccess::class,
            'feature' => EnsureFeatureEnabled::class,
            'mfa' => EnsureMfaVerified::class,
            'api.client' => AuthenticateApiClient::class,
        ]);
        // Mail clients post one-click unsubscribes (RFC 8058) without a CSRF token; the URL is signed instead.
        $middleware->validateCsrfTokens(except: ['notifications/unsubscribe/*']);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('workspace'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Connected systems always get JSON errors, whatever Accept header they send.
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());
    })->create();
