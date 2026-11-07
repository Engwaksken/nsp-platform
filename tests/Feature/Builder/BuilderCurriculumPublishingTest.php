<?php

namespace Tests\Feature\Builder;

use App\Builder\CurriculumDefinition;
use App\Builder\CurriculumService;
use App\Builder\VentureBuilderService;
use App\Models\Builder\BuilderCurriculum;
use App\Models\Builder\VentureStepSubmission;
use App\Models\Registry\Startup;
use App\Models\User;
use App\Registry\RegistryException;
use App\Registry\RegistryService;
use App\Support\Features;
use Database\Seeders\BuilderCurriculumSeeder;
use Tests\TestCase;

class BuilderCurriculumPublishingTest extends TestCase
{
    private User $owner;

    private Startup $startup;

    protected function setUp(): void
    {
        parent::setUp();
        Features::set('venture_builder', true, null);
        $this->owner = User::factory()->withRole('startup_user')->create();
        $this->startup = app(RegistryService::class)->createStartup($this->owner, [
            'legal_name' => 'Full Journey Ltd', 'display_name' => 'Full Journey', 'registration_number' => null,
            'country' => 'UG', 'year_founded' => 2024, 'description' => null, 'website' => null,
        ], []);
    }

    public function test_seeder_only_creates_provisional_unpublished_draft_and_is_idempotent(): void
    {
        $this->seed(BuilderCurriculumSeeder::class);
        $draft = BuilderCurriculum::query()->sole();
        $this->assertSame('draft', $draft->status);
        $this->assertSame('provisional', $draft->methodology_status);
        $this->assertCount(6, $draft->modules);
        $this->assertCount(24, $draft->orderedSteps());
        $this->assertNull(BuilderCurriculum::current());

        $this->seed(BuilderCurriculumSeeder::class);
        $this->assertDatabaseCount('builder_curricula', 1);
    }

    public function test_publish_rejects_wrong_counts_then_all_24_steps_can_be_completed(): void
    {
        $this->seed(BuilderCurriculumSeeder::class);
        $draft = BuilderCurriculum::query()->sole();
        $curricula = app(CurriculumService::class);
        $admin = User::factory()->withRole('platform_admin')->create();
        $definition = CurriculumDefinition::fromCurriculum($draft);
        array_pop($definition['modules'][0]['steps']);
        $curricula->updateDraft($draft, $admin, $definition);
        try {
            $curricula->publish($draft->fresh(), $admin, 'Reviewed the draft curriculum structure.');
            $this->fail('A curriculum with 23 steps must not publish.');
        } catch (RegistryException $exception) {
            $this->assertStringContainsString('exactly 24 active steps', $exception->getMessage());
        }
        $this->assertSame('draft', $draft->fresh()->status);

        $definition = require database_path('data/builder_provisional_curriculum.php');
        $curricula->updateDraft($draft->fresh(), $admin, $definition);
        $reviewer = User::factory()->withRole('builder_reviewer')->create();
        $this->actingAs($admin)->get('/admin/builder')->assertOk()->assertSee('Venture Builder curricula')->assertSee('Provisional');
        $this->actingAs($admin)->post('/admin/builder/'.$draft->id.'/publish', [
            'note' => 'Reviewed the provisional six-module, 24-step structure.',
        ])->assertRedirect('/admin/builder');
        $this->assertSame('provisional', BuilderCurriculum::current()->methodology_status);
        $publishedId = BuilderCurriculum::current()->id;
        $this->actingAs($admin)->post('/admin/builder/new')->assertRedirect();
        $this->assertSame($publishedId, BuilderCurriculum::current()->id);
        $this->assertDatabaseHas('builder_curricula', [
            'name' => 'Hive Venture Builder', 'version' => 2, 'status' => 'draft', 'copied_from_id' => $publishedId,
        ]);

        $builder = app(VentureBuilderService::class);
        $enrollment = $builder->enroll($this->startup, $this->owner);
        $steps = $enrollment->curriculum->orderedSteps();
        $this->assertCount(24, $steps);
        foreach ($steps as $step) {
            $submission = VentureStepSubmission::query()->where('enrollment_id', $enrollment->id)
                ->where('step_definition_id', $step->id)->firstOrFail();
            $responses = collect($step->fields)->mapWithKeys(fn ($field) => [$field['key'] => 'Test response for '.$field['key']])->all();
            $checklist = collect($step->checklist ?? [])->mapWithKeys(fn ($item) => [$item['key'] => true])->all();
            $builder->saveDraft($submission, $this->owner, $responses, $checklist, $submission->version);
            if ($step->evidence_requirement === 'required') {
                $builder->attachLink($submission->fresh(), $this->owner, 'Test evidence', 'https://example.org/evidence', 'Synthetic test evidence.');
            }
            $builder->submit($submission->fresh(), $this->owner);
            $builder->review($submission->fresh(), $reviewer, 'validated', 'Synthetic reviewer validated this test step.');
        }

        $progress = $builder->progress($enrollment->fresh());
        $this->assertSame(['done' => 24, 'total' => 24, 'percent' => 100], $progress);
        $this->assertDatabaseCount('venture_step_submissions', 24);
        $this->assertSame(24, VentureStepSubmission::query()->where('enrollment_id', $enrollment->id)->where('status', 'validated')->count());
    }
}
