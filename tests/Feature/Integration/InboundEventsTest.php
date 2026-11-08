<?php

namespace Tests\Feature\Integration;

use App\Models\Integration\InboxEvent;
use App\Models\Integration\ProgramRecord;
use App\Models\Registry\StartupAlias;
use App\Models\User;
use App\Registry\RegistryService;
use Illuminate\Support\Str;

class InboundEventsTest extends IntegrationTestCase
{
    public function test_signed_submission_creates_the_application_summary_and_link(): void
    {
        $startup = $this->startupWithConsent();

        $this->sendEvent($this->envelope('ims.application.submitted.v1', $startup->uuid, [
            'application_id' => '501', 'program_title' => 'Hive Accelerator', 'cohort_name' => 'Cohort 6', 'submitted_at' => '2026-11-05T09:00:00+03:00',
            // Fields IMS must never send are dropped even if they arrive.
            'score' => 87, 'reviewer_comments' => 'Weak team', 'answers' => ['q1' => 'secret'],
        ]))->assertStatus(202);

        $record = ProgramRecord::query()->firstOrFail();
        $this->assertSame(['Hive Accelerator', 'Cohort 6', 'Submitted'], [$record->program_title, $record->cohort_name, $record->status]);
        $this->assertSame($startup->uuid, StartupAlias::query()->where('source_system', 'ims')->where('external_id', '501')->value('startup_uuid'));
        $stored = json_encode(InboxEvent::query()->firstOrFail()->data);
        foreach (['87', 'Weak team', 'secret'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $stored);
        }
    }

    public function test_status_changes_and_enrolment_update_the_same_record(): void
    {
        $startup = $this->startupWithConsent();
        $this->sendEvent($this->envelope('ims.application.submitted.v1', $startup->uuid, ['application_id' => '7', 'program_title' => 'Incubation']));
        $this->sendEvent($this->envelope('ims.application.status_changed.v1', $startup->uuid, ['application_id' => '7', 'status' => 'Accepted', 'changed_at' => '2026-11-10T10:00:00+03:00']))->assertStatus(202);
        $this->sendEvent($this->envelope('ims.program.enrolled.v1', $startup->uuid, ['application_id' => '7', 'cohort_name' => 'Cohort 7', 'start_date' => '2026-12-01', 'end_date' => '2027-05-31']))->assertStatus(202);
        $this->sendEvent($this->envelope('ims.application.status_changed.v1', $startup->uuid, ['application_id' => '7', 'status' => 'Approved-ish']))->assertStatus(400);

        $record = ProgramRecord::query()->sole();
        $this->assertSame('Accepted', $record->status);
        $this->assertTrue($record->enrolled);
        $this->assertSame('2026-12-01', $record->program_start_date->toDateString());
    }

    public function test_duplicates_are_acknowledged_once_and_tampering_or_replays_rejected(): void
    {
        $startup = $this->startupWithConsent();
        $envelope = $this->envelope('ims.application.submitted.v1', $startup->uuid, ['application_id' => '9']);

        $this->sendEvent($envelope)->assertStatus(202);
        $this->sendEvent($envelope)->assertOk()->assertJsonPath('status', 'duplicate');
        $this->assertSame(1, ProgramRecord::query()->count());

        $fresh = $this->envelope('ims.application.submitted.v1', $startup->uuid, ['application_id' => '10']);
        $signedBody = json_encode($fresh);
        $tampered = str_replace('"10"', '"11"', $signedBody);
        $timestamp = time();
        $this->sendEvent($fresh, $timestamp, body: $tampered, secret: 'not-the-secret')->assertStatus(401);
        $this->sendEvent($fresh, time() - 600)->assertStatus(401);
        $this->sendEvent($fresh, secret: 'wrong-secret')->assertStatus(401);
        $this->assertSame(1, ProgramRecord::query()->count());
    }

    public function test_unknown_subjects_and_cross_startup_links_are_rejected_and_recorded(): void
    {
        $a = $this->startupWithConsent();
        $b = $this->startupWithConsent();
        $this->sendEvent($this->envelope('ims.application.submitted.v1', (string) Str::uuid(), ['application_id' => '1']))->assertStatus(400)->assertJsonPath('error', 'unknown_subject');
        $this->sendEvent($this->envelope('ims.application.submitted.v1', $a->uuid, ['application_id' => '2']))->assertStatus(202);
        $this->sendEvent($this->envelope('ims.application.status_changed.v1', $b->uuid, ['application_id' => '2', 'status' => 'Accepted']))
            ->assertStatus(400)->assertJsonPath('error', 'application_linked_to_another_startup');
        $this->sendEvent($this->envelope('nsp.something.v1', $a->uuid, ['application_id' => '3']))->assertStatus(400);

        $this->assertSame(3, InboxEvent::query()->where('status', 'rejected')->count());
        $this->assertSame($a->uuid, ProgramRecord::query()->sole()->startup_uuid);
    }

    public function test_events_for_a_merged_startup_land_on_the_surviving_record(): void
    {
        $source = $this->startupWithConsent(core: ['registration_number' => 'A1']);
        $target = $this->startupWithConsent(core: ['registration_number' => 'B2']);
        app(RegistryService::class)->merge($source, $target, User::factory()->create(), 'Same company, confirmed.');

        $this->sendEvent($this->envelope('ims.application.submitted.v1', $source->uuid, ['application_id' => '44']))->assertStatus(202);
        $this->assertSame($target->uuid, ProgramRecord::query()->sole()->startup_uuid);
    }

    public function test_only_the_startup_team_sees_its_applications(): void
    {
        $owner = User::factory()->withRole('startup_user')->create();
        $startup = $this->startupWithConsent($owner);
        $this->sendEvent($this->envelope('ims.application.submitted.v1', $startup->uuid, ['application_id' => '5', 'program_title' => 'Hive Accelerator']));
        $this->sendEvent($this->envelope('ims.application.status_changed.v1', $startup->uuid, ['application_id' => '5', 'status' => 'Rejected']));

        $this->actingAs($owner)->get("/workspace/startups/{$startup->uuid}/programs")->assertOk()->assertSee('Hive Accelerator')->assertSee('Rejected');
        $this->actingAs(User::factory()->withRole('startup_user')->create())->get("/workspace/startups/{$startup->uuid}/programs")->assertNotFound();
    }
}
