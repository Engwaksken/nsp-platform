<?php

namespace App\Integration;

use App\Models\Integration\ApiClient;
use App\Models\Integration\WebhookDelivery;
use App\Models\Registry\OutboxEvent;
use App\Models\Registry\StartupAlias;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Delivers outbox events to connected systems (contract §4.2, §4.3).
 * Runs from cron every minute; safe to run repeatedly.
 */
class OutboxDispatcher
{
    /** Event types a connected system is told about. */
    public const DELIVERED_TYPES = [
        'registry.consent.revoked.v1', 'registry.consent.granted.v1', 'registry.startup.merged.v1',
        'registry.startup.unmerged.v1', 'registry.startup.updated.v1', 'registry.membership.changed.v1',
    ];

    /** Turn new outbox events into one pending delivery per interested client. */
    public function fanOut(int $limit = 500): int
    {
        $clients = ApiClient::query()->where('is_active', true)->whereNotNull('webhook_url')->get();
        $planned = 0;
        $events = OutboxEvent::query()->whereNull('dispatched_at')->orderBy('occurred_at')->limit($limit)->get();
        foreach ($events as $event) {
            if (in_array($event->type, self::DELIVERED_TYPES, true)) {
                foreach ($clients as $client) {
                    if ($this->clientFollows($client, $event)) {
                        WebhookDelivery::query()->firstOrCreate(
                            ['event_id' => $event->event_id, 'api_client_id' => $client->id],
                            ['status' => 'pending', 'next_attempt_at' => now()],
                        );
                        $planned++;
                    }
                }
            }
            $event->forceFill(['dispatched_at' => now()])->save();
        }

        return $planned;
    }

    /** @return array{delivered: int, failed: int, dead: int} */
    public function deliverDue(int $limit = 100): array
    {
        $result = ['delivered' => 0, 'failed' => 0, 'dead' => 0];
        $due = WebhookDelivery::query()->with('event', 'client')->where('status', 'pending')
            ->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->orderBy('next_attempt_at')->limit($limit)->get();

        foreach ($due as $delivery) {
            $outcome = $this->attempt($delivery);
            $result[$outcome]++;
        }

        return $result;
    }

    /** Put a dead or failing delivery back in the queue (admin "replay"). */
    public function replay(WebhookDelivery $delivery): void
    {
        $delivery->update(['status' => 'pending', 'attempts' => 0, 'next_attempt_at' => now(), 'last_error' => null]);
    }

    private function attempt(WebhookDelivery $delivery): string
    {
        $client = $delivery->client;
        $event = $delivery->event;
        if (! $client || ! $client->is_active || ! $client->webhook_url || ! $event) {
            $delivery->update(['status' => 'dead', 'last_error' => 'Client inactive or event missing.']);

            return 'dead';
        }

        $body = json_encode([
            'event_id' => $event->event_id,
            'type' => $event->type,
            'schema_version' => $event->schema_version,
            'occurred_at' => $event->occurred_at->toIso8601String(),
            'source' => 'nsp',
            'subject_uuid' => $event->subject_uuid,
            'correlation_id' => $event->correlation_id,
            'data' => $event->data ?? (object) [],
        ], JSON_UNESCAPED_SLASHES);
        $timestamp = time();

        $status = null;
        $error = null;
        try {
            $response = Http::withBody($body, 'application/json')
                ->withHeaders([
                    'X-Hive-Timestamp' => (string) $timestamp,
                    'X-Hive-Signature' => Signature::sign($client->webhook_secret, $timestamp, $body),
                    'User-Agent' => 'HiveNSP-Webhooks/1',
                ])
                ->timeout(10)
                ->withoutRedirecting()
                ->post($client->webhook_url);
            $status = $response->status();
            if ($response->successful()) {
                $delivery->update(['status' => 'delivered', 'attempts' => $delivery->attempts + 1, 'delivered_at' => now(),
                    'last_status_code' => $status, 'last_error' => null, 'next_attempt_at' => null]);
                $event->forceFill(['published_at' => $event->published_at ?? now()])->save();

                return 'delivered';
            }
            $error = 'HTTP '.$status;
        } catch (ConnectionException $exception) {
            $error = 'Connection failed: '.mb_substr($exception->getMessage(), 0, 300);
        } catch (Throwable $exception) {
            $error = 'Error: '.mb_substr($exception->getMessage(), 0, 300);
        }

        $attempts = $delivery->attempts + 1;
        $dead = $attempts >= WebhookDelivery::MAX_ATTEMPTS;
        $delivery->update([
            'status' => $dead ? 'dead' : 'pending',
            'attempts' => $attempts,
            'last_status_code' => $status,
            'last_error' => $error,
            'next_attempt_at' => $dead ? null : now()->addMinutes(WebhookDelivery::delayAfter($attempts)),
        ]);
        $event->forceFill(['attempts' => $event->attempts + 1, 'last_error' => $error])->save();

        return $dead ? 'dead' : 'failed';
    }

    /** A client only hears about startups that are linked to its system (they connected or applied there). */
    private function clientFollows(ApiClient $client, OutboxEvent $event): bool
    {
        $uuids = array_filter([$event->subject_uuid, $event->data['source_uuid'] ?? null]);

        return StartupAlias::query()->where('source_system', $client->recipient_system)->whereIn('startup_uuid', $uuids)->exists();
    }
}
