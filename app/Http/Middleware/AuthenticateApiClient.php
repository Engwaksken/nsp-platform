<?php

namespace App\Http\Middleware;

use App\Models\Integration\ApiClient;
use App\Support\Features;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-to-server authentication: "Authorization: Bearer {client_id}.{secret}"
 * (contract §1). Usage: ->middleware('api.client:registry.read').
 */
class AuthenticateApiClient
{
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        if (! Features::enabled('ims_integration')) {
            return response()->json(['error' => 'not_found'], 404);
        }
        $token = (string) $request->bearerToken();
        [$clientId, $secret] = array_pad(explode('.', $token, 2), 2, '');
        $client = $clientId !== '' ? ApiClient::query()->where('client_id', $clientId)->first() : null;
        // Hash::check runs even for unknown clients so timing does not reveal valid client IDs.
        $valid = $client ? $client->checkSecret($secret) : password_verify($secret, '$2y$12$'.str_repeat('a', 53));

        if (! $client || ! $valid || ! $client->is_active) {
            return response()->json(['error' => 'invalid_client'], 401, ['WWW-Authenticate' => 'Bearer']);
        }
        if (! $client->hasScope($scope)) {
            return response()->json(['error' => 'insufficient_scope', 'scope' => $scope], 403);
        }
        if (! $client->last_used_at || $client->last_used_at->lt(now()->subMinute())) {
            $client->forceFill(['last_used_at' => now()])->save();
        }
        $request->attributes->set('api_client', $client);

        return $next($request);
    }
}
