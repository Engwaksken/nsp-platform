<?php

namespace Tests\Feature\Marketplace;

use App\Mail\ServiceNotice;
use App\Models\Marketplace\Inquiry;
use App\Models\Marketplace\Listing;
use App\Models\Marketplace\Report;
use App\Models\Registry\Startup;
use App\Models\Registry\StartupMembership;
use App\Models\User;
use App\Registry\RegistryService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketplaceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    /** @return array{0: User, 1: Startup} */
    private function founderWithStartup(string $name = 'Green Fields', array $core = []): array
    {
        $user = User::factory()->withRole('startup_user')->create();
        $startup = app(RegistryService::class)->createStartup($user, array_merge([
            'legal_name' => $name.' Ltd', 'display_name' => $name, 'registration_number' => 'UG-'.$name,
            'country' => 'UG', 'year_founded' => 2022, 'description' => 'Solar dryers for farmers.', 'website' => 'https://'.Str::slug($name).'.test',
        ], $core), ['sector' => 'Agriculture & Agritech', 'headquarters_location' => 'Masaka', 'contact_email' => 'private@greenfields.test',
            'revenue_band' => '50m_200m', 'traction_summary' => 'Secret traction numbers']);

        return [$user, $startup->fresh('profile')];
    }

    private function publishedListing(User $founder, Startup $startup, array $overrides = []): Listing
    {
        $this->actingAs($founder)->post("/workspace/startups/{$startup->uuid}/marketplace/listings", array_merge([
            'kind' => 'offer', 'category' => 'energy', 'title' => 'Solar crop dryer', 'summary' => 'Dries 200 kg of maize a day.',
            'description' => 'Built in Masaka. Delivery across Uganda.', 'price_note' => 'UGX 2.5M',
        ], $overrides))->assertSessionHasNoErrors();
        $listing = Listing::query()->latest()->firstOrFail();
        $this->actingAs($founder)->post("/workspace/startups/{$startup->uuid}/marketplace/listings/{$listing->slug}/status", ['status' => 'published'])->assertSessionHasNoErrors();

        return $listing->fresh();
    }

    public function test_directory_shows_only_consented_startups_and_only_consented_fields(): void
    {
        [$founder, $startup] = $this->founderWithStartup();
        [$quietFounder, $quiet] = $this->founderWithStartup('Quiet Co');
        auth()->logout();

        $this->get('/startups')->assertOk()->assertDontSee('Green Fields');
        $this->get("/startups/{$startup->uuid}")->assertNotFound();

        app(RegistryService::class)->grantConsent($startup, $founder, 'nsp_directory');
        $this->get('/startups')->assertSee('Green Fields')->assertDontSee('Quiet Co');
        $this->get("/startups/{$startup->uuid}")->assertOk()->assertSee('Solar dryers for farmers.')->assertSee('https://green-fields.test')
            ->assertSee('Masaka')->assertDontSee('private@greenfields.test')->assertDontSee('Secret traction numbers')->assertDontSee('UG-Green Fields');
        $this->get("/startups/{$quiet->uuid}")->assertNotFound();

        app(RegistryService::class)->revokeConsent($startup, $founder, 'nsp_directory');
        $this->get('/startups')->assertDontSee('Green Fields');
    }

    public function test_listings_are_drafts_until_published_and_only_editors_manage_them(): void
    {
        [$founder, $startup] = $this->founderWithStartup();
        $this->actingAs($founder)->post("/workspace/startups/{$startup->uuid}/marketplace/listings", [
            'kind' => 'request', 'category' => 'logistics', 'title' => 'Need a cold-chain truck', 'summary' => 'Weekly Masaka to Kampala.', 'description' => 'From March.',
        ])->assertSessionHasNoErrors();
        $draft = Listing::query()->firstOrFail();
        auth()->logout();
        $this->get('/marketplace')->assertOk()->assertDontSee('Need a cold-chain truck');
        $this->get("/marketplace/{$draft->slug}")->assertNotFound();

        $viewer = User::factory()->withRole('startup_user')->create();
        StartupMembership::query()->create(['startup_uuid' => $startup->uuid, 'user_id' => $viewer->id, 'role' => 'viewer', 'status' => 'active']);
        $this->actingAs($viewer)->post("/workspace/startups/{$startup->uuid}/marketplace/listings/{$draft->slug}/status", ['status' => 'published'])->assertForbidden();

        $this->actingAs($founder)->post("/workspace/startups/{$startup->uuid}/marketplace/listings/{$draft->slug}/status", ['status' => 'published'])->assertSessionHasNoErrors();
        auth()->logout();
        $this->get('/marketplace?kind=request')->assertSee('Need a cold-chain truck');
        $this->get('/marketplace?kind=offer')->assertDontSee('Need a cold-chain truck');
        $this->get("/marketplace/{$draft->slug}")->assertOk()->assertSee('Weekly Masaka to Kampala.')->assertSee('Sign in to send a message');

        [$other, $otherStartup] = $this->founderWithStartup('Other Co');
        $this->actingAs($other)->get("/workspace/startups/{$otherStartup->uuid}/marketplace/listings/{$draft->slug}/edit")->assertNotFound();
    }

    public function test_inquiry_conversation_stays_between_the_two_sides_without_email_content(): void
    {
        [$founder, $startup] = $this->founderWithStartup();
        $listing = $this->publishedListing($founder, $startup);
        $buyer = User::factory()->withRole('startup_user')->create(['name' => 'Bob Buyer']);

        $this->actingAs($founder)->post("/marketplace/{$listing->slug}/inquire", ['subject' => 'x', 'body' => 'y'])->assertSessionHasErrors('inquiry');
        $this->actingAs($buyer)->post("/marketplace/{$listing->slug}/inquire", ['subject' => 'Price for 3 dryers', 'body' => 'Can you deliver to Gulu?'])->assertSessionHasNoErrors();
        $inquiry = Inquiry::query()->firstOrFail();
        $this->actingAs($buyer)->post("/marketplace/{$listing->slug}/inquire", ['subject' => 'Again', 'body' => 'Again'])->assertSessionHasErrors('inquiry');

        Mail::assertQueued(ServiceNotice::class, fn ($m) => $m->hasTo($founder->email) && ! str_contains(implode(' ', $m->lines), 'Gulu'));

        $this->actingAs($founder)->get("/workspace/startups/{$startup->uuid}/marketplace")->assertSee('Price for 3 dryers')->assertSee('New');
        $this->actingAs($founder)->get("/marketplace/messages/{$inquiry->uuid}")->assertOk()->assertSee('Can you deliver to Gulu?')->assertDontSee($buyer->email);
        $this->actingAs($founder)->post("/marketplace/messages/{$inquiry->uuid}", ['body' => 'Yes, UGX 150k delivery.'])->assertSessionHasNoErrors();
        Mail::assertQueued(ServiceNotice::class, fn ($m) => $m->hasTo($buyer->email) && ! str_contains(implode(' ', $m->lines), '150k'));
        $this->actingAs($buyer)->get('/marketplace/messages')->assertSee('New reply');
        $this->actingAs($buyer)->get("/marketplace/messages/{$inquiry->uuid}")->assertSee('UGX 150k delivery')->assertDontSee($founder->email);

        // A team viewer reads but cannot reply; outsiders cannot open it.
        $viewer = User::factory()->withRole('startup_user')->create();
        StartupMembership::query()->create(['startup_uuid' => $startup->uuid, 'user_id' => $viewer->id, 'role' => 'viewer', 'status' => 'active']);
        $this->actingAs($viewer)->get("/marketplace/messages/{$inquiry->uuid}")->assertOk()->assertSee('Can you deliver to Gulu?');
        $this->actingAs($viewer)->post("/marketplace/messages/{$inquiry->uuid}", ['body' => 'hi'])->assertSessionHasErrors('inquiry');
        $stranger = User::factory()->withRole('startup_user')->create();
        $this->actingAs($stranger)->get("/marketplace/messages/{$inquiry->uuid}")->assertNotFound();
        $this->actingAs($stranger)->post("/marketplace/messages/{$inquiry->uuid}", ['body' => 'hi'])->assertNotFound();

        // Staff cannot read it without a report.
        $manager = User::factory()->withRole('ecosystem_manager')->create();
        $this->actingAs($manager)->get("/marketplace/messages/{$inquiry->uuid}")->assertNotFound();

        $this->actingAs($buyer)->post("/marketplace/messages/{$inquiry->uuid}/close")->assertSessionHasNoErrors();
        $this->actingAs($founder)->post("/marketplace/messages/{$inquiry->uuid}", ['body' => 'Still there?'])->assertSessionHasErrors('inquiry');
    }

    public function test_reported_conversation_becomes_a_dispute_staff_can_resolve(): void
    {
        [$founder, $startup] = $this->founderWithStartup();
        $listing = $this->publishedListing($founder, $startup);
        $buyer = User::factory()->withRole('startup_user')->create();
        $this->actingAs($buyer)->post("/marketplace/{$listing->slug}/inquire", ['subject' => 'Order', 'body' => 'I paid a deposit of UGX 500k.']);
        $inquiry = Inquiry::query()->firstOrFail();

        $this->actingAs($buyer)->post("/marketplace/messages/{$inquiry->uuid}/report", ['reason' => 'not_delivered', 'details' => 'No delivery after 6 weeks.'])->assertSessionHasNoErrors();
        $report = Report::query()->firstOrFail();

        $editor = User::factory()->withRole('content_editor')->create();
        $this->actingAs($editor)->get('/admin/marketplace')->assertForbidden();

        $manager = User::factory()->withRole('ecosystem_manager')->create();
        $this->actingAs($manager)->get('/admin/marketplace')->assertOk()->assertSee('Paid or agreed but not delivered')->assertSee('Open reports');
        $this->actingAs($manager)->get("/admin/marketplace/reports/{$report->id}")->assertOk()->assertSee('I paid a deposit of UGX 500k.');
        $this->assertDatabaseHas('audit_logs', ['action' => 'marketplace.dispute_viewed']);

        $this->actingAs($manager)->post("/admin/marketplace/reports/{$report->id}", ['status' => 'resolved'])->assertSessionHasErrors('resolution_note');
        $this->actingAs($manager)->post("/admin/marketplace/reports/{$report->id}", [
            'status' => 'resolved', 'resolution_note' => 'Seller did not respond to Hive.', 'remove_listing' => '1',
        ])->assertRedirect('/admin/marketplace');
        $this->assertSame('removed', $listing->fresh()->status);
        Mail::assertQueued(ServiceNotice::class, fn ($m) => $m->hasTo($buyer->email) && str_contains($m->subjectLine, 'report was reviewed'));

        auth()->logout();
        $this->get("/marketplace/{$listing->slug}")->assertNotFound();
        $this->actingAs($founder)->get("/workspace/startups/{$startup->uuid}/marketplace")->assertSee('Seller did not respond to Hive.');
        $this->actingAs($founder)->post("/workspace/startups/{$startup->uuid}/marketplace/listings/{$listing->slug}/status", ['status' => 'published'])->assertForbidden();

        $this->actingAs($manager)->post("/admin/marketplace/listings/{$listing->slug}/restore")->assertSessionHasNoErrors();
        $this->assertSame('draft', $listing->fresh()->status);
    }

    public function test_listing_reports_and_expiry(): void
    {
        [$founder, $startup] = $this->founderWithStartup();
        $listing = $this->publishedListing($founder, $startup, ['expires_on' => now()->addDays(5)->toDateString()]);
        $visitor = User::factory()->withRole('startup_user')->create();
        $this->actingAs($visitor)->post("/marketplace/{$listing->slug}/report", ['reason' => 'scam'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('marketplace_reports', ['listing_uuid' => $listing->uuid, 'inquiry_uuid' => null, 'reason' => 'scam']);

        $this->travel(6)->days();
        auth()->logout();
        $this->get('/marketplace')->assertDontSee('Solar crop dryer');
        $this->get("/marketplace/{$listing->slug}")->assertNotFound();
    }
}
