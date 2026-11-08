<?php

namespace Tests\Feature\Ecosystem;

use App\Ecosystem\Eligibility;
use App\Ecosystem\Recommender;
use App\Mail\OpportunityDeadlineReminder;
use App\Models\Ecosystem\Opportunity;
use App\Models\Ecosystem\OpportunityInterest;
use App\Models\User;
use App\Registry\RegistryService;
use App\Support\Features;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OpportunitiesTest extends TestCase
{
    private function opportunity(array $attributes = []): Opportunity
    {
        $opportunity = new Opportunity(array_merge([
            'type' => 'grant', 'title' => 'Agritech Seed Grant', 'summary' => 'Up to UGX 20M for agritech startups.',
            'deadline_at' => now()->addDays(10), 'status' => 'published',
        ], $attributes));
        $opportunity->published_at = now();
        $opportunity->save();

        return $opportunity;
    }

    private function founderWithStartup(array $core = [], array $profile = []): array
    {
        $user = User::factory()->withRole('startup_user')->create();
        $startup = app(RegistryService::class)->createStartup($user, array_merge([
            'legal_name' => 'Green Fields Ltd', 'display_name' => 'Green Fields', 'registration_number' => 'UG1',
            'country' => 'UG', 'year_founded' => now()->year - 2, 'description' => null, 'website' => null,
        ], $core), array_merge(['sector' => 'Agriculture & Agritech'], $profile));

        return [$user, $startup->fresh('profile')];
    }

    public function test_public_list_shows_only_open_published_opportunities(): void
    {
        $this->opportunity();
        $this->opportunity(['title' => 'Closed Prize', 'deadline_at' => now()->subDay()]);
        $this->opportunity(['title' => 'Draft Call', 'status' => 'draft']);
        $this->opportunity(['title' => 'Unreviewed Tip', 'status' => 'pending_review']);

        $this->get('/opportunities')->assertOk()->assertSee('Agritech Seed Grant')
            ->assertDontSee('Closed Prize')->assertDontSee('Draft Call')->assertDontSee('Unreviewed Tip');
        $this->get('/opportunities/draft-call')->assertNotFound();
        $this->get('/opportunities?type=event')->assertDontSee('Agritech Seed Grant');
    }

    public function test_submissions_are_moderated_and_rejections_need_a_reason(): void
    {
        $user = User::factory()->withRole('startup_user')->create();
        $this->actingAs($user)->post('/opportunities', [
            'type' => 'competition', 'title' => 'Youth Innovation Prize', 'summary' => 'A prize for young founders.',
            'apply_url' => 'javascript:alert(1)',
        ])->assertSessionHasErrors('apply_url');
        $this->actingAs($user)->post('/opportunities', [
            'type' => 'competition', 'title' => 'Youth Innovation Prize', 'summary' => 'A prize for young founders.',
            'apply_url' => 'https://prize.example/apply', 'accepts_interest' => 1, 'capacity' => 5,
        ])->assertRedirect('/opportunities');

        $submitted = Opportunity::query()->sole();
        $this->assertSame('pending_review', $submitted->status);
        $this->assertFalse($submitted->accepts_interest, 'Users cannot turn on registration themselves');
        $this->get('/opportunities')->assertDontSee('Youth Innovation Prize');

        $manager = User::factory()->withRole('ecosystem_manager')->create();
        $this->actingAs($manager)->post("/admin/opportunities/{$submitted->slug}/moderate", ['decision' => 'reject'])->assertSessionHasErrors('note');
        $this->actingAs($manager)->post("/admin/opportunities/{$submitted->slug}/moderate", ['decision' => 'publish'])->assertSessionHasNoErrors();
        $this->get('/opportunities')->assertSee('Youth Innovation Prize');
        $this->assertDatabaseHas('audit_logs', ['action' => 'opportunity.publish', 'actor_id' => $manager->id]);
    }

    public function test_eligibility_is_explained_including_unknowns(): void
    {
        [, $startup] = $this->founderWithStartup();
        $opportunity = $this->opportunity(['eligibility' => [
            'sectors' => ['Agriculture & Agritech'], 'countries' => ['KE'], 'stages' => ['Growth'], 'registered_only' => true, 'max_age_years' => 5,
        ]]);

        $checks = collect(Eligibility::check($opportunity, $startup))->keyBy('rule');
        $this->assertTrue($checks['Sector']['met']);
        $this->assertFalse($checks['Country']['met']);
        $this->assertNull($checks['Stage']['met'], 'No growth assessment yet, so stage is unknown');
        $this->assertTrue($checks['Registered company']['met']);
        $this->assertTrue($checks['Company age']['met']);
    }

    public function test_recommendations_skip_failed_rules_explain_themselves_and_respect_feedback(): void
    {
        [$user, $startup] = $this->founderWithStartup();
        $match = $this->opportunity(['title' => 'Agri Match', 'eligibility' => ['sectors' => ['Agriculture & Agritech'], 'countries' => ['UG']]]);
        $this->opportunity(['title' => 'Health Only', 'eligibility' => ['sectors' => ['Health']]]);
        $open = $this->opportunity(['title' => 'Open Call', 'deadline_at' => now()->addDays(40)]);

        $titles = array_map(fn ($r) => $r['opportunity']->title, Recommender::forStartup($startup, $user));
        $this->assertSame(['Agri Match', 'Open Call'], $titles, 'Better matches first; failed rules excluded');
        $this->actingAs($user)->get("/workspace/startups/{$startup->uuid}/opportunities")->assertOk()
            ->assertSee('Matches sector')->assertSee('Open to all startups')->assertDontSee('Health Only');

        $this->actingAs($user)->post("/workspace/startups/{$startup->uuid}/opportunities/{$match->slug}/feedback", ['feedback' => 'not_relevant'])->assertRedirect();
        $this->assertSame(['Open Call'], array_map(fn ($r) => $r['opportunity']->title, Recommender::forStartup($startup, $user)));
        $this->actingAs(User::factory()->create())->get("/workspace/startups/{$startup->uuid}/opportunities")->assertNotFound();
    }

    public function test_bookmarks_trigger_one_deadline_reminder(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $soon = $this->opportunity(['title' => 'Soon', 'deadline_at' => now()->addDays(2)]);
        $later = $this->opportunity(['title' => 'Later', 'deadline_at' => now()->addDays(20)]);
        $this->actingAs($user)->post("/opportunities/{$soon->slug}/bookmark")->assertRedirect();
        $this->actingAs($user)->post("/opportunities/{$later->slug}/bookmark")->assertRedirect();

        $this->artisan('opportunities:send-reminders')->assertSuccessful();
        $this->artisan('opportunities:send-reminders')->assertSuccessful();

        Mail::assertQueued(OpportunityDeadlineReminder::class, 1);
        Mail::assertQueued(OpportunityDeadlineReminder::class, fn ($mail) => $mail->opportunity->is($soon) && $mail->hasTo($user->email));

        // Saving again removes the bookmark.
        $this->actingAs($user)->post("/opportunities/{$later->slug}/bookmark");
        $this->assertDatabaseMissing('opportunity_bookmarks', ['opportunity_id' => $later->id]);
    }

    public function test_registration_respects_capacity_and_startup_membership(): void
    {
        $event = $this->opportunity(['type' => 'event', 'title' => 'Pitch Night', 'accepts_interest' => true, 'capacity' => 1,
            'deadline_at' => null, 'starts_at' => now()->addWeek(), 'ends_at' => now()->addWeek()->addHours(3)]);
        [$founder, $startup] = $this->founderWithStartup();
        [, $otherStartup] = $this->founderWithStartup(['registration_number' => 'UG2', 'legal_name' => 'Other Co', 'display_name' => 'Other']);

        $this->actingAs($founder)->post("/opportunities/{$event->slug}/register", ['startup_uuid' => $otherStartup->uuid])->assertForbidden();
        $this->actingAs($founder)->post("/opportunities/{$event->slug}/register", ['startup_uuid' => $startup->uuid, 'message' => 'Looking forward'])->assertSessionHasNoErrors();
        $this->assertSame($startup->uuid, OpportunityInterest::query()->sole()->startup_uuid);

        $this->actingAs(User::factory()->create())->post("/opportunities/{$event->slug}/register")->assertSessionHasErrors('register');
        $this->actingAs($founder)->post("/opportunities/{$event->slug}/withdraw");
        $this->actingAs(User::factory()->create())->post("/opportunities/{$event->slug}/register")->assertSessionHasNoErrors();
    }

    public function test_staff_export_is_audited_and_safe_for_spreadsheets(): void
    {
        $event = $this->opportunity(['type' => 'event', 'accepts_interest' => true, 'deadline_at' => null, 'starts_at' => now()->addDay()]);
        $attendee = User::factory()->create(['name' => '=HYPERLINK("http://evil")']);
        OpportunityInterest::query()->create(['opportunity_id' => $event->id, 'user_id' => $attendee->id, 'status' => 'registered']);
        $manager = User::factory()->withRole('ecosystem_manager')->create();

        $csv = $this->actingAs($manager)->get("/admin/opportunities/{$event->slug}/registrations.csv")->assertOk()->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertDatabaseHas('audit_logs', ['action' => 'opportunity.registrations_exported']);
        $this->actingAs(User::factory()->withRole('content_editor')->create())->get('/admin/opportunities')->assertForbidden();
    }

    public function test_staff_create_publish_and_see_every_admin_screen(): void
    {
        $manager = User::factory()->withRole('ecosystem_manager')->create();
        $this->actingAs($manager)->post('/admin/opportunities', [
            'type' => 'event', 'title' => 'Founder Clinic', 'summary' => 'Ask the experts.', 'starts_at' => now()->addDays(3)->format('Y-m-d\TH:i'),
            'accepts_interest' => 1, 'capacity' => 20, 'sectors' => ['Health'], 'publish' => 1,
        ])->assertRedirect();
        $opportunity = Opportunity::query()->where('title', 'Founder Clinic')->sole();
        $this->assertSame('published', $opportunity->status);
        $this->assertSame(['sectors' => ['Health']], $opportunity->eligibility);

        foreach (['/admin/opportunities', '/admin/opportunities?tab=pending_review', '/admin/opportunities/create',
            "/admin/opportunities/{$opportunity->slug}/edit", "/admin/opportunities/{$opportunity->slug}/registrations"] as $path) {
            $this->actingAs($manager)->get($path)->assertOk();
        }
    }

    public function test_everything_is_hidden_when_the_feature_is_off(): void
    {
        $this->opportunity();
        Features::set('opportunities', false, null);

        $this->get('/opportunities')->assertNotFound();
        $this->get('/')->assertDontSee('href="'.url('/opportunities').'"', false);
    }
}
