<?php

namespace Tests\Feature\Experts;

use App\Mail\ServiceNotice;
use App\Models\Experts\Engagement;
use App\Models\Experts\ExpertProfile;
use App\Models\Experts\ExpertReview;
use App\Models\Experts\ExpertSession;
use App\Models\Registry\Startup;
use App\Models\Registry\StartupMembership;
use App\Models\User;
use App\Registry\RegistryService;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ExpertsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    /** @return array{0: User, 1: Startup} */
    private function founderWithStartup(string $name = 'Green Fields'): array
    {
        $user = User::factory()->withRole('startup_user')->create();
        $startup = app(RegistryService::class)->createStartup($user, [
            'legal_name' => $name.' Ltd', 'display_name' => $name, 'registration_number' => 'UG-'.$name,
            'country' => 'UG', 'year_founded' => 2022, 'description' => null, 'website' => null,
        ], ['sector' => 'Agriculture & Agritech']);

        return [$user, $startup->fresh('profile')];
    }

    private function expert(string $status = 'verified', array $attributes = [], string $name = 'Esther Expert'): User
    {
        $user = User::factory()->withRole('startup_user')->create(['name' => $name]);
        $profile = new ExpertProfile(array_merge([
            'headline' => 'Finance lead, 10 years in agribusiness', 'bio' => 'I help startups with bookkeeping and fundraising.',
            'kind' => 'both', 'expertise' => ['finance', 'fundraising'], 'sectors' => ['Agriculture & Agritech'], 'languages' => ['en'],
            'offers_free' => true, 'max_active' => 5, 'is_listed' => true,
        ], $attributes));
        $profile->user_id = $user->id;
        $profile->status = $status;
        $profile->save();

        return $user;
    }

    private function requestHelp(User $founder, Startup $startup, User $expert, string $type = 'consultation'): Engagement
    {
        $this->actingAs($founder)->post("/workspace/startups/{$startup->uuid}/experts", [
            'expert_user_id' => $expert->id, 'type' => $type, 'topic' => 'Investor-ready financials', 'details' => 'We need a cash-flow model.',
        ])->assertSessionHasNoErrors();

        return Engagement::query()->latest()->firstOrFail();
    }

    public function test_directory_lists_only_verified_listed_experts_with_reasons(): void
    {
        [$founder, $startup] = $this->founderWithStartup();
        $this->expert();
        $this->expert('pending', [], 'Pat Pending');
        $this->expert('verified', ['is_listed' => false], 'Hidden Helen');
        $this->expert('suspended', [], 'Sam Suspended');

        $this->actingAs($founder)->get("/workspace/startups/{$startup->uuid}/experts?areas[]=finance")->assertOk()
            ->assertSee('Esther Expert')->assertSee('Knows Agriculture &amp; Agritech', false)->assertSee('Expertise: Finance and bookkeeping')
            ->assertDontSee('Pat Pending')->assertDontSee('Hidden Helen')->assertDontSee('Sam Suspended');
        $this->actingAs($founder)->get("/workspace/startups/{$startup->uuid}/experts?areas[]=legal")->assertDontSee('Esther Expert');

        $stranger = User::factory()->withRole('startup_user')->create();
        $this->actingAs($stranger)->get("/workspace/startups/{$startup->uuid}/experts")->assertNotFound();
    }

    public function test_request_accept_and_contacts_are_shared_only_after_acceptance(): void
    {
        [$founder, $startup] = $this->founderWithStartup();
        $expert = $this->expert();
        $engagement = $this->requestHelp($founder, $startup, $expert);
        Mail::assertQueued(ServiceNotice::class, fn ($m) => $m->hasTo($expert->email));

        // Before acceptance, neither side sees the other's email.
        $this->actingAs($expert)->get("/engagements/{$engagement->uuid}")->assertOk()->assertSee('cash-flow model')->assertDontSee($founder->email);
        $this->actingAs($founder)->get("/engagements/{$engagement->uuid}")->assertOk()->assertDontSee($expert->email);

        // The founder cannot accept on the expert's behalf; duplicates are refused.
        $this->actingAs($founder)->post("/engagements/{$engagement->uuid}/decision", ['decision' => 'accept'])->assertSessionHasErrors('engagement');
        $this->actingAs($founder)->post("/workspace/startups/{$startup->uuid}/experts", [
            'expert_user_id' => $expert->id, 'type' => 'consultation', 'topic' => 'Again',
        ])->assertSessionHasErrors('expert');

        $this->actingAs($expert)->post("/engagements/{$engagement->uuid}/decision", ['decision' => 'accept'])->assertSessionHasNoErrors();
        $this->assertSame('active', $engagement->fresh()->status);
        Mail::assertQueued(ServiceNotice::class, fn ($m) => $m->hasTo($founder->email) && str_contains($m->subjectLine, 'accepted'));
        $this->actingAs($expert)->get("/engagements/{$engagement->uuid}")->assertSee($founder->email);
        $this->actingAs($founder)->get("/engagements/{$engagement->uuid}")->assertSee($expert->email);

        // Outsiders and other experts get "not found".
        $other = $this->expert('verified', [], 'Other Expert');
        $this->actingAs($other)->get("/engagements/{$engagement->uuid}")->assertNotFound();
    }

    public function test_unverified_experts_and_consultant_only_mentoring_are_refused(): void
    {
        [$founder, $startup] = $this->founderWithStartup();
        $pending = $this->expert('pending');
        $this->actingAs($founder)->post("/workspace/startups/{$startup->uuid}/experts", [
            'expert_user_id' => $pending->id, 'type' => 'consultation', 'topic' => 'Help',
        ])->assertSessionHasErrors('expert');

        $consultant = $this->expert('verified', ['kind' => 'consultant'], 'Connie Consultant');
        $this->actingAs($founder)->post("/workspace/startups/{$startup->uuid}/experts", [
            'expert_user_id' => $consultant->id, 'type' => 'mentorship', 'topic' => 'Help',
        ])->assertSessionHasErrors('expert');
        $this->assertSame(0, Engagement::query()->count());
    }

    public function test_sessions_actions_and_note_visibility(): void
    {
        [$founder, $startup] = $this->founderWithStartup();
        $expert = $this->expert();
        $engagement = $this->requestHelp($founder, $startup, $expert);
        $this->actingAs($expert)->post("/engagements/{$engagement->uuid}/decision", ['decision' => 'accept']);

        $this->actingAs($founder)->post("/engagements/{$engagement->uuid}/sessions", [
            'scheduled_at' => now()->addDays(3)->format('Y-m-d\TH:i'), 'duration_minutes' => 60, 'mode' => 'online', 'location' => 'javascript:alert(1)',
        ])->assertSessionHasErrors('location');
        $this->actingAs($founder)->post("/engagements/{$engagement->uuid}/sessions", [
            'scheduled_at' => now()->addDays(3)->format('Y-m-d\TH:i'), 'duration_minutes' => 60, 'mode' => 'online', 'location' => 'https://meet.example.test/abc',
        ])->assertSessionHasNoErrors();
        Mail::assertQueued(ServiceNotice::class, fn ($m) => $m->hasTo($expert->email) && str_contains($m->subjectLine, 'Session planned'));
        $session = ExpertSession::query()->firstOrFail();

        $this->actingAs($expert)->put("/engagements/{$engagement->uuid}/sessions/{$session->uuid}", [
            'shared_notes' => 'Agreed to build a 12-month cash-flow model.', 'private_notes' => 'Founder seems unsure about margins.', 'status' => 'completed',
        ])->assertSessionHasNoErrors();
        $this->actingAs($founder)->put("/engagements/{$engagement->uuid}/sessions/{$session->uuid}", [
            'private_notes' => 'Ask about the grant budget next time.',
        ])->assertSessionHasNoErrors();

        $session->refresh();
        $this->assertSame('completed', $session->status);
        $this->assertSame('Founder seems unsure about margins.', $session->expert_notes);
        $this->assertSame('Ask about the grant budget next time.', $session->startup_notes);
        $this->assertArrayNotHasKey('expert_notes', $session->toArray());

        $this->actingAs($founder)->get("/engagements/{$engagement->uuid}")->assertSee('12-month cash-flow model')
            ->assertSee('Ask about the grant budget')->assertDontSee('unsure about margins');
        $this->actingAs($expert)->get("/engagements/{$engagement->uuid}")->assertSee('unsure about margins')->assertDontSee('grant budget next time');

        // A team viewer can read shared notes but not change anything, and never sees the expert's notes.
        $viewer = User::factory()->withRole('startup_user')->create();
        StartupMembership::query()->create(['startup_uuid' => $startup->uuid, 'user_id' => $viewer->id, 'role' => 'viewer', 'status' => 'active']);
        $this->actingAs($viewer)->get("/engagements/{$engagement->uuid}")->assertOk()->assertSee('12-month cash-flow model')->assertDontSee('unsure about margins');
        $this->actingAs($viewer)->put("/engagements/{$engagement->uuid}/sessions/{$session->uuid}", ['shared_notes' => 'overwritten'])->assertSessionHasErrors('engagement');

        // Staff overview never shows notes.
        $manager = User::factory()->withRole('ecosystem_manager')->create();
        $this->actingAs($manager)->get('/admin/engagements')->assertOk()->assertSee('Investor-ready financials')
            ->assertDontSee('cash-flow model')->assertDontSee('unsure about margins');

        $this->actingAs($founder)->post("/engagements/{$engagement->uuid}/actions", ['title' => 'Draft cash-flow model', 'owner' => 'startup', 'due_on' => now()->addWeek()->toDateString()])->assertSessionHasNoErrors();
        $action = $engagement->actions()->firstOrFail();
        $this->actingAs($expert)->post("/engagements/{$engagement->uuid}/actions/{$action->id}/toggle")->assertSessionHasNoErrors();
        $this->assertNotNull($action->fresh()->done_at);
    }

    public function test_completion_and_one_review_by_the_startup_with_ratings_shown_after_three(): void
    {
        [$founder, $startup] = $this->founderWithStartup();
        $expert = $this->expert();
        $engagement = $this->requestHelp($founder, $startup, $expert);
        $this->actingAs($founder)->post("/engagements/{$engagement->uuid}/review", ['rating' => 5])->assertSessionHasErrors('engagement');
        $this->actingAs($expert)->post("/engagements/{$engagement->uuid}/decision", ['decision' => 'accept']);
        $this->actingAs($expert)->post("/engagements/{$engagement->uuid}/complete", ['outcome' => 'Model built.'])->assertSessionHasNoErrors();

        $this->actingAs($expert)->post("/engagements/{$engagement->uuid}/review", ['rating' => 5])->assertSessionHasErrors('engagement');
        $this->actingAs($founder)->post("/engagements/{$engagement->uuid}/review", ['rating' => 4, 'helped' => 1, 'comment' => 'Very practical'])->assertSessionHasNoErrors();
        $this->actingAs($founder)->post("/engagements/{$engagement->uuid}/review", ['rating' => 1])->assertSessionHasErrors('engagement');
        $this->actingAs($expert)->get("/engagements/{$engagement->uuid}")->assertSee('Very practical');

        // One review is not shown as a rating in the directory.
        [$other] = $this->founderWithStartup('Other Co');
        $otherStartup = Startup::query()->where('display_name', 'Other Co')->firstOrFail();
        $this->actingAs($other)->get("/workspace/startups/{$otherStartup->uuid}/experts")->assertDontSee('Rated');

        foreach ([5, 3] as $i => $rating) {
            [$f, $s] = $this->founderWithStartup('Startup '.$i);
            $e = Engagement::query()->create(['startup_uuid' => $s->uuid, 'expert_user_id' => $expert->id, 'type' => 'consultation', 'source' => 'founder_request', 'topic' => 'x', 'status' => 'completed']);
            ExpertReview::query()->create(['engagement_uuid' => $e->uuid, 'startup_uuid' => $s->uuid, 'expert_user_id' => $expert->id, 'reviewer_id' => $f->id, 'rating' => $rating]);
        }
        $this->actingAs($other)->get("/workspace/startups/{$otherStartup->uuid}/experts")->assertSee('Rated 4/5 by 3 startups')->assertDontSee('Very practical');
    }

    public function test_staff_verify_experts_and_assign_mentors(): void
    {
        $applicant = User::factory()->withRole('startup_user')->create(['name' => 'Mona Mentor']);
        $this->actingAs($applicant)->put('/experts/profile', [
            'headline' => 'Marketing mentor', 'bio' => 'Twenty years in FMCG marketing.', 'kind' => 'mentor', 'expertise' => ['marketing'], 'max_active' => 3,
        ])->assertSessionHasNoErrors();
        $profile = ExpertProfile::query()->where('user_id', $applicant->id)->firstOrFail();
        $this->assertSame('pending', $profile->status);
        $this->actingAs($applicant)->get('/experts')->assertRedirect('/experts/profile');

        $editor = User::factory()->withRole('content_editor')->create();
        $this->actingAs($editor)->get('/admin/experts')->assertForbidden();

        $manager = User::factory()->withRole('ecosystem_manager')->create();
        $this->actingAs($manager)->put("/admin/experts/{$profile->id}", ['status' => 'rejected'])->assertSessionHasErrors('verification_note');
        $this->actingAs($manager)->put("/admin/experts/{$profile->id}", ['status' => 'verified'])->assertSessionHasNoErrors();
        $this->assertTrue($applicant->fresh()->hasRole('expert'));

        [$founder, $startup] = $this->founderWithStartup();
        $this->actingAs($manager)->post('/admin/engagements', [
            'startup_uuid' => $startup->uuid, 'expert_user_id' => $applicant->id, 'topic' => 'Go-to-market plan',
        ])->assertSessionHasNoErrors();
        $engagement = Engagement::query()->firstOrFail();
        $this->assertSame(['mentorship', 'staff_assignment', 'active'], [$engagement->type, $engagement->source, $engagement->status]);
        Mail::assertQueued(ServiceNotice::class, fn ($m) => $m->hasTo($applicant->email));
        Mail::assertQueued(ServiceNotice::class, fn ($m) => $m->hasTo($founder->email) && str_contains($m->subjectLine, 'mentor'));
        $this->actingAs($applicant)->get('/experts')->assertOk()->assertSee('Green Fields')->assertSee('assigned by Hive');

        // A suspended expert loses access to their engagements' actions and the dashboard.
        $this->actingAs($manager)->put("/admin/experts/{$profile->id}", ['status' => 'suspended', 'verification_note' => 'Paused'])->assertSessionHasNoErrors();
        $this->assertFalse($applicant->fresh()->hasRole('expert'));
        $this->actingAs($applicant)->get('/experts')->assertRedirect('/experts/profile');
        $this->actingAs($applicant)->post("/engagements/{$engagement->uuid}/actions", ['title' => 'x', 'owner' => 'expert'])->assertSessionHasErrors('engagement');
    }
}
