<?php

namespace Tests\Feature\Integration;

use App\Integration\OutboxDispatcher;
use App\Integration\Signature;
use App\Models\Integration\WebhookDelivery;
use App\Models\Registry\Startup;
use App\Models\Registry\StartupAlias;
use App\Models\User;
use App\Registry\RegistryService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class OutboxDeliveryTest extends IntegrationTestCase
{
    private function linkedStartup(User $owner): Startup
    {
        $startup = $this->startupWithConsent($owner);
        StartupAlias::query()->create(['source_system' => 'ims', 'external_id' => 'APP-'.$startup->uuid, 'startup_uuid' => $startup->uuid]);

        return $startup;
    }

    public function test_consent_revocation_is_delivered_signed_to_the_linked_system_only(): void
    {
        Http::fake(['ims.test/*' => Http::response(['status' => 'ok'], 202)]);
        $owner = User::factory()->withRole('startup_user')->create();
        $linked = $this->linkedStartup($owner);
        $unlinked = $this->startupWithConsent($owner);
        $registry = app(RegistryService::class);
        $registry->revokeConsent($linked, $owner, 'ims_prefill');
        $registry->revokeConsent($unlinked, $owner, 'ims_prefill');

        $this->artisan('registry:deliver-outbox')->assertSuccessful();

        // The linked startup's "granted" and "revoked" events go out; nothing for the unlinked one.
        Http::assertSentCount(2);
        Http::assertNotSent(fn (Request $request) => json_decode($request->body(), true)['subject_uuid'] === $unlinked->uuid);
        Http::assertSent(function (Request $request) use ($linked) {
            $body = $request->body();
            $payload = json_decode($body, true);

            return $request->url() === 'https://ims.test/nsp-webhook'
                && $payload['type'] === 'registry.consent.revoked.v1'
                && $payload['subject_uuid'] === $linked->uuid
                && $payload['data']['purpose'] === 'ims_prefill'
                && Signature::verify($this->webhookSecret, $request->header('X-Hive-Timestamp')[0], $request->header('X-Hive-Signature')[0], $body);
        });
        $this->assertSame(['delivered'], WebhookDelivery::query()->pluck('status')->unique()->values()->all());

        // Running again sends nothing new.
        $this->artisan('registry:deliver-outbox')->assertSuccessful();
        Http::assertSentCount(2);
    }

    public function test_failures_back_off_then_die_and_can_be_replayed(): void
    {
        // IMS is down until $imsStatus changes (a second Http::fake would not replace the first stub).
        $imsStatus = 503;
        Http::fake(['ims.test/*' => function () use (&$imsStatus) {
            return Http::response($imsStatus === 200 ? '' : 'down', $imsStatus);
        }]);
        $owner = User::factory()->withRole('startup_user')->create();
        app(RegistryService::class)->revokeConsent($this->linkedStartup($owner), $owner, 'ims_prefill');
        $dispatcher = app(OutboxDispatcher::class);

        $dispatcher->fanOut();
        $dispatcher->deliverDue();
        $delivery = WebhookDelivery::query()->whereHas('event', fn ($q) => $q->where('type', 'registry.consent.revoked.v1'))->sole();
        $this->assertSame(['pending', 1, 503], [$delivery->status, $delivery->attempts, $delivery->last_status_code]);
        $this->assertEqualsWithDelta(now()->addMinute()->timestamp, $delivery->next_attempt_at->timestamp, 5);

        // Not due yet: nothing is sent.
        $dispatcher->deliverDue();
        $this->assertSame(1, $delivery->fresh()->attempts);

        for ($i = 0; $i < 10; $i++) {
            $this->travel(13)->hours();
            $dispatcher->deliverDue();
        }
        $delivery->refresh();
        $this->assertSame('dead', $delivery->status);
        $this->assertSame(WebhookDelivery::MAX_ATTEMPTS, $delivery->attempts);

        $admin = User::factory()->withRole('platform_admin')->create();
        $this->actingAs($admin)->get('/admin/integrations')->assertOk()->assertSee('registry.consent.revoked.v1')->assertSee('HTTP 503');
        $this->actingAs($admin)->post("/admin/integrations/deliveries/{$delivery->id}/replay")->assertRedirect();

        $imsStatus = 200;
        $dispatcher->deliverDue();
        $this->assertSame('delivered', $delivery->fresh()->status);
    }

    public function test_backoff_schedule_matches_the_contract(): void
    {
        $this->assertSame([1, 5, 15, 60, 240, 720, 720], array_map(fn ($n) => WebhookDelivery::delayAfter($n), range(1, 7)));
    }

    public function test_events_of_unlinked_startups_create_no_deliveries(): void
    {
        Http::fake();
        $owner = User::factory()->withRole('startup_user')->create();
        app(RegistryService::class)->revokeConsent($this->startupWithConsent($owner), $owner, 'ims_prefill');

        $this->artisan('registry:deliver-outbox')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame(0, WebhookDelivery::query()->count());
    }
}
