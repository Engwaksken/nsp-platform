<?php

namespace Tests\Feature\Analytics;

use App\Analytics\Kpis;
use App\Models\Registry\Startup;
use App\Models\Registry\StartupMembership;
use App\Models\Talent\JobApplication;
use App\Models\Talent\TalentProfile;
use App\Models\Talent\Vacancy;
use App\Models\User;
use App\Registry\RegistryService;
use App\Support\Csv;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InsightsTest extends TestCase
{
    /** @return array{0: User, 1: Startup} */
    private function founderWithStartup(string $name = 'Green Fields'): array
    {
        $user = User::factory()->withRole('startup_user')->create();
        $startup = app(RegistryService::class)->createStartup($user, [
            'legal_name' => $name.' Ltd', 'display_name' => $name, 'registration_number' => 'UG-'.$name,
            'country' => 'UG', 'year_founded' => 2022, 'description' => null, 'website' => null,
        ], ['sector' => 'Agriculture & Agritech', 'contact_email' => 'private@'.str($name)->slug().'.test', 'traction_summary' => 'Secret traction']);

        return [$user, $startup->fresh('profile')];
    }

    private function login(User $user, $when): void
    {
        DB::table('login_audits')->insert(['user_id' => $user->id, 'method' => 'password', 'success' => true, 'created_at' => $when]);
    }

    public function test_kpis_follow_their_definitions(): void
    {
        [$a] = $this->founderWithStartup('Alpha');
        [$b] = $this->founderWithStartup('Beta');
        $this->founderWithStartup('Gamma');
        $this->login($a, now()->subDays(40));
        $this->login($a, now()->subDays(2));
        $this->login($b, now()->subDays(45));
        DB::table('login_audits')->insert(['user_id' => $b->id, 'method' => 'password', 'success' => false, 'created_at' => now()->subDay()]);
        DB::table('startup_program_records')->insert([
            'startup_uuid' => Startup::query()->where('display_name', 'Alpha')->value('uuid'), 'source_system' => 'ims', 'external_application_id' => 'A1',
            'status' => 'Accepted', 'enrolled' => true, 'submitted_at' => now()->subDays(5), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $kpis = Kpis::forDays(30)->all();
        $this->assertSame(3, $kpis['registrations_startups']['value']);
        $this->assertSame(1, $kpis['active_startups']['value'], 'Only Alpha had a successful sign-in in the period.');
        $this->assertSame('50%', $kpis['retention']['value'], 'Alpha and Beta signed in the period before; only Alpha came back (failed sign-ins do not count).');
        $this->assertSame(1, $kpis['program_participation']['value']);
        $this->assertSame('0%', $kpis['vgi_completion']['value']);
        foreach ($kpis as $kpi) {
            $this->assertStringNotContainsString('@', (string) $kpi['value'].$kpi['detail'], 'KPIs never contain contact details.');
        }
    }

    public function test_insights_are_for_permitted_staff_and_exports_are_audited_and_scoped(): void
    {
        $this->founderWithStartup();
        $editor = User::factory()->withRole('content_editor')->create();
        $this->actingAs($editor)->get('/admin/insights')->assertForbidden();

        $auditor = User::factory()->withRole('auditor')->create();
        $this->actingAs($auditor)->get('/admin/insights?days=90')->assertOk()->assertSee('Active startups')->assertSee('Last 90 days');
        $this->actingAs($auditor)->get('/admin/insights/kpis.csv?days=90')->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'insights.kpis_exported']);
        $this->actingAs($auditor)->get('/admin/insights/startups.csv')->assertForbidden();

        $manager = User::factory()->withRole('ecosystem_manager')->create();
        $csv = $this->actingAs($manager)->get('/admin/insights/startups.csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('Green Fields', $csv);
        $this->assertStringNotContainsString('private@green-fields.test', $csv);
        $this->assertStringNotContainsString('Secret traction', $csv);
        $this->assertDatabaseHas('audit_logs', ['action' => 'insights.startups_exported']);
    }

    public function test_startup_overview_and_data_export_are_team_scoped(): void
    {
        [$founder, $startup] = $this->founderWithStartup();
        [$otherFounder, $other] = $this->founderWithStartup('Other Co');

        $this->actingAs($founder)->get("/workspace/startups/{$startup->uuid}/overview")->assertOk()->assertSee('Profile complete')->assertSee('Download our data');
        $this->actingAs($otherFounder)->get("/workspace/startups/{$startup->uuid}/overview")->assertNotFound();

        $json = $this->actingAs($founder)->get("/workspace/startups/{$startup->uuid}/export")->assertOk()->json();
        $this->assertSame('Green Fields', $json['startup']['display_name']);
        $this->assertSame('private@green-fields.test', $json['profile']['contact_email']);
        $this->assertSame($founder->email, $json['team'][0]['email']);
        $this->assertStringNotContainsString('Other Co', json_encode($json));
        $this->assertDatabaseHas('audit_logs', ['action' => 'workspace.data_exported']);

        $viewer = User::factory()->withRole('startup_user')->create();
        StartupMembership::query()->create(['startup_uuid' => $startup->uuid, 'user_id' => $viewer->id, 'role' => 'viewer', 'status' => 'active']);
        $this->actingAs($viewer)->get("/workspace/startups/{$startup->uuid}/overview")->assertOk()->assertDontSee('Download our data');
        $this->actingAs($viewer)->get("/workspace/startups/{$startup->uuid}/export")->assertForbidden();
        $this->actingAs($otherFounder)->get("/workspace/startups/{$startup->uuid}/export")->assertNotFound();
    }

    public function test_applications_csv_has_no_cv_or_team_notes_and_is_safe_for_spreadsheets(): void
    {
        [$founder, $startup] = $this->founderWithStartup();
        $vacancy = Vacancy::query()->create(['startup_uuid' => $startup->uuid, 'title' => 'Sales', 'type' => 'full_time', 'description' => 'x', 'created_by' => $founder->id]);
        $vacancy->forceFill(['status' => 'open', 'published_at' => now()])->save();
        $candidate = User::factory()->withRole('startup_user')->create(['name' => '=HYPERLINK("http://evil")']);
        $profile = new TalentProfile(['headline' => 'Seller', 'visibility' => 'hidden']);
        $profile->user_id = $candidate->id;
        $profile->save();
        $application = JobApplication::query()->create(['vacancy_uuid' => $vacancy->uuid, 'startup_uuid' => $startup->uuid, 'applicant_id' => $candidate->id, 'status' => 'shortlisted', 'cv_path' => 'talent/applications/x.pdf', 'cv_name' => 'cv.pdf']);
        $application->forceFill(['team_notes' => 'Private team view'])->save();

        $csv = $this->actingAs($founder)->get("/workspace/startups/{$startup->uuid}/talent/vacancies/{$vacancy->slug}/applications.csv")->assertOk()->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString($candidate->email, $csv);
        $this->assertStringNotContainsString('Private team view', $csv);
        $this->assertStringNotContainsString('cv.pdf', $csv);
        $this->assertDatabaseHas('audit_logs', ['action' => 'talent.applications_exported']);

        [$otherFounder, $other] = $this->founderWithStartup('Other Co');
        $this->actingAs($otherFounder)->get("/workspace/startups/{$other->uuid}/talent/vacancies/{$vacancy->slug}/applications.csv")->assertNotFound();
        $this->assertSame("'-1", Csv::safe('-1'));
        $this->assertSame('plain', Csv::safe('plain'));
    }

    public function test_admin_dashboard_shows_only_counts_the_role_may_see(): void
    {
        $editor = User::factory()->withRole('content_editor')->create();
        $this->actingAs($editor)->get('/admin')->assertOk()->assertDontSee('Investors to verify')->assertDontSee('Active startups');

        $manager = User::factory()->withRole('ecosystem_manager')->create();
        $this->actingAs($manager)->get('/admin')->assertOk()->assertSee('Investors to verify')->assertSee('Experts to verify')->assertSee('KPIs and reports');
    }
}
