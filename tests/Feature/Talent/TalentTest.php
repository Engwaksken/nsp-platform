<?php

namespace Tests\Feature\Talent;

use App\Mail\ServiceNotice;
use App\Models\Registry\Startup;
use App\Models\Registry\StartupMembership;
use App\Models\Talent\Interview;
use App\Models\Talent\JobApplication;
use App\Models\Talent\TalentProfile;
use App\Models\Talent\Vacancy;
use App\Models\User;
use App\Registry\RegistryService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TalentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
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

    private function openVacancy(User $founder, Startup $startup, array $overrides = []): Vacancy
    {
        $this->actingAs($founder)->post("/workspace/startups/{$startup->uuid}/talent/vacancies", array_merge([
            'title' => 'Field Sales Officer', 'type' => 'full_time', 'description' => 'Sell solar dryers to farmer groups.',
            'skills' => 'Sales, Luganda, Motorcycle riding', 'location' => 'Masaka',
        ], $overrides))->assertSessionHasNoErrors();
        $vacancy = Vacancy::query()->latest()->firstOrFail();
        $this->actingAs($founder)->post("/workspace/startups/{$startup->uuid}/talent/vacancies/{$vacancy->slug}/status", ['status' => 'open'])->assertSessionHasNoErrors();

        return $vacancy->fresh();
    }

    private function candidate(string $visibility = 'hidden', bool $withCv = true, string $name = 'Carol Candidate'): User
    {
        $user = User::factory()->withRole('startup_user')->create(['name' => $name]);
        $payload = ['headline' => 'Sales rep, 3 years in agri inputs', 'skills' => 'Sales, Luganda', 'work_types' => ['full_time'],
            'location' => 'Masaka', 'country' => 'UG', 'visibility' => $visibility, 'open_to_work' => '1'];
        if ($withCv) {
            $payload['cv'] = UploadedFile::fake()->createWithContent('carol-cv.pdf', "%PDF-1.4\n%%EOF");
        }
        $this->actingAs($user)->put('/talent/profile', $payload)->assertSessionHasNoErrors();

        return $user;
    }

    public function test_public_board_lists_only_open_vacancies_of_active_startups(): void
    {
        [$founder, $startup] = $this->founderWithStartup();
        $open = $this->openVacancy($founder, $startup);
        $this->actingAs($founder)->post("/workspace/startups/{$startup->uuid}/talent/vacancies", [
            'title' => 'Draft Role', 'type' => 'internship', 'description' => 'Not yet.',
        ]);
        $draft = Vacancy::query()->where('title', 'Draft Role')->firstOrFail();

        auth()->logout();
        $this->get('/jobs')->assertOk()->assertSee('Field Sales Officer')->assertSee('Green Fields')->assertDontSee('Draft Role');
        $this->get("/jobs/{$open->slug}")->assertOk()->assertSee('Sell solar dryers')->assertSee('Sign in to apply');
        $this->get("/jobs/{$draft->slug}")->assertNotFound();
        $this->get('/jobs?type=internship')->assertDontSee('Field Sales Officer');

        $startup->forceFill(['status' => 'archived'])->save();
        $this->get('/jobs')->assertDontSee('Field Sales Officer');
    }

    public function test_apply_shares_a_cv_copy_with_editors_only_and_withdrawal_deletes_it(): void
    {
        [$founder, $startup] = $this->founderWithStartup();
        $vacancy = $this->openVacancy($founder, $startup);
        $candidate = $this->candidate();

        $this->actingAs($candidate)->post("/jobs/{$vacancy->slug}/apply", ['cover_note' => 'I sold inputs for 3 years.'])->assertSessionHasErrors('consent');
        $this->actingAs($candidate)->post("/jobs/{$vacancy->slug}/apply", ['cover_note' => 'I sold inputs for 3 years.', 'consent' => '1'])->assertSessionHasNoErrors();
        $this->actingAs($candidate)->post("/jobs/{$vacancy->slug}/apply", ['consent' => '1'])->assertSessionHasErrors('apply');
        $application = JobApplication::query()->firstOrFail();
        Storage::disk('local')->assertExists($application->cv_path);
        Mail::assertQueued(ServiceNotice::class, fn ($m) => $m->hasTo($founder->email));

        // Editors see the application and CV; a viewer on the team and outsiders do not.
        $this->actingAs($founder)->get("/workspace/startups/{$startup->uuid}/talent/applications/{$application->uuid}")->assertOk()
            ->assertSee('I sold inputs for 3 years.')->assertSee($candidate->email);
        $this->actingAs($founder)->get("/workspace/startups/{$startup->uuid}/talent/applications/{$application->uuid}/cv")->assertOk();
        $viewer = User::factory()->withRole('startup_user')->create();
        StartupMembership::query()->create(['startup_uuid' => $startup->uuid, 'user_id' => $viewer->id, 'role' => 'viewer', 'status' => 'active']);
        $this->actingAs($viewer)->get("/workspace/startups/{$startup->uuid}/talent/applications/{$application->uuid}/cv")->assertForbidden();
        [$otherFounder, $otherStartup] = $this->founderWithStartup('Other Co');
        $this->actingAs($otherFounder)->get("/workspace/startups/{$otherStartup->uuid}/talent/applications/{$application->uuid}/cv")->assertNotFound();
        $this->actingAs($otherFounder)->get("/workspace/startups/{$startup->uuid}/talent/applications/{$application->uuid}")->assertNotFound();

        // Replacing the profile CV does not change what was sent.
        $sent = $application->cv_path;
        $this->actingAs($candidate)->put('/talent/profile', ['headline' => 'Sales rep', 'visibility' => 'hidden', 'cv' => UploadedFile::fake()->createWithContent('new.pdf', "%PDF-1.4\n%%EOF")]);
        Storage::disk('local')->assertExists($sent);

        $this->actingAs($candidate)->post("/talent/applications/{$application->uuid}/withdraw")->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($sent);
        $this->assertSame('withdrawn', $application->fresh()->status);
        $this->actingAs($founder)->put("/workspace/startups/{$startup->uuid}/talent/applications/{$application->uuid}", ['status' => 'shortlisted'])->assertSessionHasErrors('talent');
    }

    public function test_pipeline_moves_notify_the_candidate_and_team_notes_stay_private(): void
    {
        [$founder, $startup] = $this->founderWithStartup();
        $vacancy = $this->openVacancy($founder, $startup);
        $candidate = $this->candidate();
        $this->actingAs($candidate)->post("/jobs/{$vacancy->slug}/apply", ['consent' => '1']);
        $application = JobApplication::query()->firstOrFail();

        $this->actingAs($founder)->put("/workspace/startups/{$startup->uuid}/talent/applications/{$application->uuid}", [
            'status' => 'shortlisted', 'message' => 'Thanks, we would like to talk.', 'team_notes' => 'Strong Luganda, weak on reporting',
        ])->assertSessionHasNoErrors();
        Mail::assertQueued(ServiceNotice::class, fn ($m) => $m->hasTo($candidate->email) && in_array('Message from the team: Thanks, we would like to talk.', $m->lines, true));
        $this->assertDatabaseHas('hiring_pipeline_events', ['application_uuid' => $application->uuid, 'from_status' => 'submitted', 'to_status' => 'shortlisted']);

        $this->actingAs($candidate)->get('/talent')->assertOk()->assertSee('Shortlisted')->assertSee('we would like to talk')->assertDontSee('weak on reporting');
        $this->assertArrayNotHasKey('team_notes', $application->fresh()->toArray());

        // Interviews: proposed by the team, answered by the candidate only.
        $this->actingAs($founder)->post("/workspace/startups/{$startup->uuid}/talent/applications/{$application->uuid}/interviews", [
            'scheduled_at' => now()->addDays(2)->format('Y-m-d\TH:i'), 'duration_minutes' => 45, 'mode' => 'online', 'location' => 'http://insecure.test',
        ])->assertSessionHasErrors('location');
        $this->actingAs($founder)->post("/workspace/startups/{$startup->uuid}/talent/applications/{$application->uuid}/interviews", [
            'scheduled_at' => now()->addDays(2)->format('Y-m-d\TH:i'), 'duration_minutes' => 45, 'mode' => 'in_person', 'location' => 'Hive office, Masaka',
        ])->assertSessionHasNoErrors();
        $interview = Interview::query()->firstOrFail();
        $this->assertSame('interview', $application->fresh()->status);

        $this->actingAs($founder)->post("/talent/interviews/{$interview->uuid}/response", ['decision' => 'confirm'])->assertNotFound();
        $this->actingAs($candidate)->post("/talent/interviews/{$interview->uuid}/response", ['decision' => 'confirm'])->assertSessionHasNoErrors();
        $this->assertSame('confirmed', $interview->fresh()->status);
        Mail::assertQueued(ServiceNotice::class, fn ($m) => $m->hasTo($founder->email) && str_contains($m->subjectLine, 'Interview confirmed'));
    }

    public function test_search_shows_only_opted_in_candidates_without_contact_details_and_invites_work(): void
    {
        [$founder, $startup] = $this->founderWithStartup();
        $vacancy = $this->openVacancy($founder, $startup);
        $visible = $this->candidate('startups', true, 'Vera Visible');
        $hidden = $this->candidate('hidden', true, 'Hank Hidden');

        $profile = TalentProfile::query()->where('user_id', $visible->id)->firstOrFail();
        $this->assertNotNull($profile->consented_at);
        $this->assertSame(config('nsp.talent.consent_version'), $profile->consent_version);

        $this->actingAs($founder)->get("/workspace/startups/{$startup->uuid}/talent/candidates?q=sales")->assertOk()
            ->assertSee('Vera Visible')->assertDontSee('Hank Hidden')->assertDontSee($visible->email)->assertDontSee('carol-cv.pdf');

        $this->actingAs($founder)->post("/workspace/startups/{$startup->uuid}/talent/candidates/invite", ['vacancy_uuid' => $vacancy->uuid, 'user_id' => $hidden->id])
            ->assertSessionHasErrors('talent');
        $this->actingAs($founder)->post("/workspace/startups/{$startup->uuid}/talent/candidates/invite", ['vacancy_uuid' => $vacancy->uuid, 'user_id' => $visible->id, 'message' => 'You fit our Masaka team.'])
            ->assertSessionHasNoErrors();
        Mail::assertQueued(ServiceNotice::class, fn ($m) => $m->hasTo($visible->email) && str_contains($m->subjectLine, 'invites you'));
        $this->actingAs($visible)->get('/talent')->assertSee('Field Sales Officer');

        // Opting out clears consent and removes them from search at once.
        $this->actingAs($visible)->put('/talent/profile', ['headline' => 'Sales rep', 'visibility' => 'hidden'])->assertSessionHasNoErrors();
        $this->assertNull($profile->fresh()->consented_at);
        $this->actingAs($founder)->get("/workspace/startups/{$startup->uuid}/talent/candidates")->assertDontSee('Vera Visible');

        // Team viewers cannot search.
        $viewer = User::factory()->withRole('startup_user')->create();
        StartupMembership::query()->create(['startup_uuid' => $startup->uuid, 'user_id' => $viewer->id, 'role' => 'viewer', 'status' => 'active']);
        $this->actingAs($viewer)->get("/workspace/startups/{$startup->uuid}/talent/candidates")->assertForbidden();
    }

    public function test_cv_upload_checks_content_and_retention_purge_deletes_old_copies(): void
    {
        $user = User::factory()->withRole('startup_user')->create();
        $path = tempnam(sys_get_temp_dir(), 'nsp');
        file_put_contents($path, '<html><script>alert(1)</script></html>');
        $this->actingAs($user)->put('/talent/profile', ['headline' => 'x', 'visibility' => 'hidden', 'cv' => new UploadedFile($path, 'cv.pdf', 'application/pdf', null, true)])
            ->assertSessionHasErrors('cv');

        [$founder, $startup] = $this->founderWithStartup();
        $vacancy = $this->openVacancy($founder, $startup);
        $candidate = $this->candidate();
        $this->actingAs($candidate)->post("/jobs/{$vacancy->slug}/apply", ['consent' => '1']);
        $application = JobApplication::query()->firstOrFail();
        $copy = $application->cv_path;

        $this->actingAs($founder)->post("/workspace/startups/{$startup->uuid}/talent/vacancies/{$vacancy->slug}/status", ['status' => 'filled']);
        $this->artisan('talent:purge-cvs')->assertSuccessful();
        Storage::disk('local')->assertExists($copy);

        $this->travel(config('nsp.talent.cv_retention_days') + 1)->days();
        $this->artisan('talent:purge-cvs')->expectsOutputToContain('Purged 1')->assertSuccessful();
        Storage::disk('local')->assertMissing($copy);
        $this->assertNotNull($application->fresh()->cv_purged_at);
    }

    public function test_viewers_cannot_post_vacancies_and_staff_can_take_them_down(): void
    {
        [$founder, $startup] = $this->founderWithStartup();
        $viewer = User::factory()->withRole('startup_user')->create();
        StartupMembership::query()->create(['startup_uuid' => $startup->uuid, 'user_id' => $viewer->id, 'role' => 'viewer', 'status' => 'active']);
        $this->actingAs($viewer)->post("/workspace/startups/{$startup->uuid}/talent/vacancies", ['title' => 'x', 'type' => 'gig', 'description' => 'y'])->assertForbidden();

        $vacancy = $this->openVacancy($founder, $startup);
        $editor = User::factory()->withRole('content_editor')->create();
        $this->actingAs($editor)->get('/admin/vacancies')->assertForbidden();

        $manager = User::factory()->withRole('ecosystem_manager')->create();
        $this->actingAs($manager)->get('/admin/vacancies')->assertOk()->assertSee('Field Sales Officer');
        $this->actingAs($manager)->post("/admin/vacancies/{$vacancy->slug}/moderate", ['action' => 'remove'])->assertSessionHasErrors('moderation_note');
        $this->actingAs($manager)->post("/admin/vacancies/{$vacancy->slug}/moderate", ['action' => 'remove', 'moderation_note' => 'Unpaid full-time work'])->assertSessionHasNoErrors();
        $this->get("/jobs/{$vacancy->slug}")->assertNotFound();
        $this->actingAs($founder)->get("/workspace/startups/{$startup->uuid}/talent")->assertSee('Unpaid full-time work');
        $this->actingAs($founder)->post("/workspace/startups/{$startup->uuid}/talent/vacancies/{$vacancy->slug}/status", ['status' => 'open'])->assertForbidden();
    }

    public function test_team_members_cannot_apply_to_their_own_startup_and_deleting_profile_removes_cv(): void
    {
        [$founder, $startup] = $this->founderWithStartup();
        $vacancy = $this->openVacancy($founder, $startup);
        $this->actingAs($founder)->put('/talent/profile', ['headline' => 'Founder', 'visibility' => 'hidden'])->assertSessionHasNoErrors();
        $this->actingAs($founder)->post("/jobs/{$vacancy->slug}/apply", ['consent' => '1'])->assertSessionHasErrors('apply');

        $candidate = $this->candidate();
        $cv = TalentProfile::query()->where('user_id', $candidate->id)->value('cv_path');
        Storage::disk('local')->assertExists($cv);
        $this->actingAs($candidate)->delete('/talent/profile')->assertRedirect('/talent/profile');
        Storage::disk('local')->assertMissing($cv);
        $this->assertSame(0, TalentProfile::query()->where('user_id', $candidate->id)->count());
    }
}
