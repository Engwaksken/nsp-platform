<?php

namespace App\Http\Controllers;

use App\Operations\Health;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HealthController extends Controller
{
    /**
     * For uptime monitors: GET /health with the header `Authorization: Bearer <NSP_HEALTH_TOKEN>`.
     * Returns 200 when nothing fails, 503 otherwise, with check names and statuses only.
     * Answers 404 unless a token is configured, so the endpoint does not exist by default.
     */
    public function endpoint(Request $request, Health $health): JsonResponse
    {
        $token = (string) config('nsp.health_token');
        abort_if($token === '', 404);
        abort_unless(hash_equals($token, (string) $request->bearerToken()), 401);
        $checks = $health->checks();
        $overall = $health->overall($checks);

        return response()->json([
            'status' => $overall,
            'checks' => collect($checks)->mapWithKeys(fn ($c) => [$c['key'] => $c['status']]),
            'time' => now()->toIso8601String(),
        ], $overall === 'fail' ? 503 : 200, ['Cache-Control' => 'no-store']);
    }

    public function admin(Health $health): View
    {
        $checks = $health->checks();

        return view('admin.system', ['checks' => $checks, 'overall' => $health->overall($checks)]);
    }
}
