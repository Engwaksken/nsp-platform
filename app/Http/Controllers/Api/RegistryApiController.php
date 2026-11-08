<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Integration\ConsentedFields;
use App\Integration\EventIngestor;
use App\Integration\Signature;
use App\Models\Integration\ApiClient;
use App\Models\Integration\ConnectCode;
use App\Models\Integration\InboxEvent;
use App\Models\Integration\WebhookDelivery;
use App\Models\Registry\Startup;
use App\Models\Registry\StartupAlias;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Registry API v1 for connected systems (docs/integration-contract-v1.md). */
class RegistryApiController extends Controller
{
    private function client(Request $request): ApiClient
    {
        return $request->attributes->get('api_client');
    }

    /** Exchange a connect code for the consented startup profile (contract §2). */
    public function token(Request $request): JsonResponse
    {
        $client = $this->client($request);
        $code = (string) $request->input('code', '');
        $redirectUri = (string) $request->input('redirect_uri', '');

        $connect = DB::transaction(function () use ($client, $code, $redirectUri) {
            $connect = ConnectCode::query()->where('code_hash', hash('sha256', $code))->lockForUpdate()->first();
            if (! $connect || $connect->used_at || $connect->api_client_id !== $client->id) {
                return null;
            }
            // Any attempt uses the code up, so a wrong redirect URI or a late retry cannot be followed by another try.
            $connect->update(['used_at' => now()]);
            if ($connect->expires_at->isPast() || ! hash_equals($connect->redirect_uri, $redirectUri)) {
                return null;
            }

            return $connect;
        });
        if (! $connect) {
            return response()->json(['error' => 'invalid_grant'], 400);
        }

        $startup = Startup::query()->find($connect->startup_uuid);
        $consent = $startup ? ConsentedFields::consent($startup, $client) : null;
        if (! $startup || $startup->status !== 'active' || ! $consent || ! $startup->membershipOf($connect->user)) {
            return response()->json(['error' => 'invalid_grant'], 400);
        }
        Audit::log('integration.profile_shared', $startup, ['client' => $client->client_id]);

        return response()->json(ConsentedFields::payload($startup->load('profile'), $consent) + [
            'user' => ['name' => $connect->user->name, 'email' => $connect->user->email],
        ]);
    }

    public function showStartup(Request $request, string $uuid): JsonResponse
    {
        $client = $this->client($request);
        $startup = Startup::query()->with('profile')->find($uuid);
        if (! $startup || $startup->status === 'archived') {
            return response()->json(['error' => 'not_found'], 404);
        }
        if ($startup->status === 'merged' && $startup->merged_into_uuid) {
            return response()->json(['error' => 'moved', 'startup_uuid' => $startup->merged_into_uuid], 301,
                ['Location' => url('/api/v1/startups/'.$startup->merged_into_uuid)]);
        }
        $consent = ConsentedFields::consent($startup, $client);
        if (! $consent) {
            return response()->json(['error' => 'consent_required'], 403);
        }

        return response()->json(ConsentedFields::payload($startup, $consent));
    }

    public function link(Request $request): JsonResponse
    {
        $client = $this->client($request);
        $data = $request->validate([
            'startup_uuid' => ['required', 'uuid'],
            'external_id' => ['required', 'string', 'max:100'],
        ]);
        $startup = Startup::query()->where('status', 'active')->find($data['startup_uuid']);
        if (! $startup) {
            return response()->json(['error' => 'not_found'], 404);
        }
        if (! ConsentedFields::consent($startup, $client)) {
            return response()->json(['error' => 'consent_required'], 403);
        }
        $existing = StartupAlias::query()->where('source_system', $client->recipient_system)->where('external_id', $data['external_id'])->first();
        if ($existing) {
            return $existing->startup_uuid === $startup->uuid
                ? response()->json(['status' => 'linked'])
                : response()->json(['error' => 'linked_to_another_startup'], 409);
        }
        StartupAlias::query()->create(['source_system' => $client->recipient_system, 'external_id' => $data['external_id'], 'startup_uuid' => $startup->uuid]);
        Audit::log('integration.alias_linked', $startup, ['client' => $client->client_id, 'external_id' => $data['external_id']]);

        return response()->json(['status' => 'linked'], 201);
    }

    /** Signed events from the connected system (contract §4.1). */
    public function events(Request $request, EventIngestor $ingestor): JsonResponse
    {
        $client = $this->client($request);
        $raw = $request->getContent();
        if (! Signature::verify($client->webhook_secret, $request->header('X-Hive-Timestamp'), $request->header('X-Hive-Signature'), $raw)) {
            return response()->json(['error' => 'invalid_signature'], 401);
        }
        $envelope = json_decode($raw, true);
        if (! is_array($envelope)) {
            return response()->json(['error' => 'invalid_json'], 400);
        }
        [$status, $body] = $ingestor->ingest($client, $envelope);

        return response()->json($body, $status);
    }

    public function syncStatus(Request $request, string $uuid): JsonResponse
    {
        $client = $this->client($request);
        if (! Startup::query()->find($uuid)) {
            return response()->json(['error' => 'not_found'], 404);
        }
        $deliveries = WebhookDelivery::query()->with('event:event_id,type,occurred_at')
            ->where('api_client_id', $client->id)
            ->whereHas('event', fn ($q) => $q->where('subject_uuid', $uuid))
            ->latest('id')->limit(50)->get();

        return response()->json([
            'startup_uuid' => $uuid,
            'outgoing' => [
                'pending' => $deliveries->where('status', 'pending')->count(),
                'dead' => $deliveries->where('status', 'dead')->count(),
                'recent' => $deliveries->take(10)->map(fn (WebhookDelivery $d) => [
                    'event_id' => $d->event_id, 'type' => $d->event?->type, 'status' => $d->status,
                    'attempts' => $d->attempts, 'last_error' => $d->last_error,
                ])->values(),
            ],
            'incoming' => InboxEvent::query()->where('api_client_id', $client->id)->where('subject_uuid', $uuid)
                ->latest('received_at')->limit(10)->get(['event_id', 'type', 'status', 'error', 'received_at']),
        ]);
    }
}
