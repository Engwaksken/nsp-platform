<?php

namespace Tests\Feature\Builder;

use App\Models\Builder\BuilderCurriculum;
use App\Models\Builder\BuilderModule;
use App\Models\Builder\BuilderStepDefinition;
use App\Models\Builder\VentureStepSubmission;
use App\Models\Registry\Startup;
use App\Models\User;
use App\Registry\RegistryService;
use App\Support\Features;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VentureBuilderTest extends TestCase
{
    private User $owner;

    private Startup $startup;

    private BuilderStepDefinition $firstStep;

    private BuilderStepDefinition $secondStep;

    protected function setUp(): void
    {
        parent::setUp();
        Features::set('venture_builder', true, null);
        $this->owner = User::factory()->withRole('startup_user')->create();
        $this->startup = app(RegistryService::class)->createStartup($this->owner, [
            'legal_name' => 'Builder Test Ltd', 'display_name' => 'Builder Test', 'registration_number' => null,
            'country' => 'UG', 'year_founded' => 2024, 'description' => null, 'website' => null,
        ], []);
        $curriculum = BuilderCurriculum::query()->create([
            'name' => 'Test curriculum', 'version' => 1, 'status' => 'published', 'methodology_status' => 'provisional',
        ]);
        $module = BuilderModule::query()->create(['curriculum_id' => $curriculum->id, 'position' => 1, 'key' => 'test_module', 'title' => 'Test module']);
        $this->firstStep = $this->makeStep($curriculum, $module, 1, 'first_step', 'First step');
        $this->secondStep = $this->makeStep($curriculum, $module, 2, 'second_step', 'Second step');
    }

    private function makeStep(BuilderCurriculum $curriculum, BuilderModule $module, int $position, string $key, string $title): BuilderStepDefinition
    {
        return BuilderStepDefinition::query()->create([
            'curriculum_id' => $curriculum->id, 'module_id' => $module->id, 'position' => $position,
            'key' => $key, 'title' => $title,
            'fields' => [['key' => 'answer', 'label' => 'Your answer', 'type' => 'textarea', 'required' => true]],
            'checklist' => [['key' => 'reviewed', 'label' => 'I reviewed this draft']],
            'evidence_requirement' => 'none', 'requires_review' => false,
        ]);
    }

    public function test_start_creates_one_pinned_enrollment_and_resumes_saved_step(): void
    {
        $this->actingAs($this->owner)->post("/workspace/startups/{$this->startup->uuid}/builder/start")->assertRedirect();
        $this->assertDatabaseCount('venture_builder_enrollments', 1);
        $submission = VentureStepSubmission::query()->where('startup_uuid', $this->startup->uuid)->where('step_key', 'first_step')->firstOrFail();

        $this->actingAs($this->owner)->put("/workspace/startups/{$this->startup->uuid}/builder/steps/{$submission->id}", [
            'version' => 1, 'responses' => ['answer' => 'Draft response'], 'checklist' => ['reviewed' => '1'],
        ])->assertRedirect();
        $this->assertSame('in_progress', $submission->fresh()->status);
        $this->assertSame(2, $submission->fresh()->version);
        $this->actingAs($this->owner)->get("/workspace/startups/{$this->startup->uuid}/builder/steps/{$submission->id}")
            ->assertOk()->assertSee('Draft response')->assertSee('I reviewed this draft');
        $this->actingAs($this->owner)->post("/workspace/startups/{$this->startup->uuid}/builder/steps/{$submission->id}/submit")
            ->assertRedirect();
        $this->assertSame('submitted', $submission->fresh()->status);
        $next = VentureStepSubmission::query()->where('startup_uuid', $this->startup->uuid)->where('step_key', 'second_step')->firstOrFail();
        $this->actingAs($this->owner)->get("/workspace/startups/{$this->startup->uuid}/builder/steps/{$next->id}")->assertOk();
        $this->actingAs($this->owner)->post("/workspace/startups/{$this->startup->uuid}/builder/start")->assertRedirect();
        $this->assertDatabaseCount('venture_builder_enrollments', 1);
    }

    public function test_steps_require_prior_submission_and_stale_draft_versions_are_rejected(): void
    {
        $this->actingAs($this->owner)->post("/workspace/startups/{$this->startup->uuid}/builder/start");
        $second = VentureStepSubmission::query()->where('startup_uuid', $this->startup->uuid)->where('step_key', $this->secondStep->key)->firstOrFail();
        $this->actingAs($this->owner)->get("/workspace/startups/{$this->startup->uuid}/builder/steps/{$second->id}")->assertNotFound();

        $first = VentureStepSubmission::query()->where('startup_uuid', $this->startup->uuid)->where('step_key', $this->firstStep->key)->firstOrFail();
        $this->actingAs($this->owner)->put("/workspace/startups/{$this->startup->uuid}/builder/steps/{$first->id}", [
            'version' => 1, 'responses' => ['answer' => 'Saved'],
        ])->assertRedirect();
        $this->actingAs($this->owner)->put("/workspace/startups/{$this->startup->uuid}/builder/steps/{$first->id}", [
            'version' => 1, 'responses' => ['answer' => 'Stale overwrite'],
        ])->assertSessionHasErrors('builder');
        $this->assertSame('Saved', $first->fresh()->responses['answer']);
        $this->assertDatabaseHas('builder_step_events', ['submission_id' => $first->id, 'action' => 'draft_saved']);
    }

    public function test_required_evidence_review_feedback_and_validation_are_enforced(): void
    {
        $this->firstStep->forceFill(['evidence_requirement' => 'required', 'requires_review' => true])->save();
        $this->actingAs($this->owner)->post("/workspace/startups/{$this->startup->uuid}/builder/start");
        $submission = VentureStepSubmission::query()->where('startup_uuid', $this->startup->uuid)->where('step_key', 'first_step')->firstOrFail();
        $this->actingAs($this->owner)->put("/workspace/startups/{$this->startup->uuid}/builder/steps/{$submission->id}", [
            'version' => 1, 'responses' => ['answer' => 'Evidence-backed response'], 'checklist' => ['reviewed' => '1'],
        ])->assertRedirect();
        $this->actingAs($this->owner)->post("/workspace/startups/{$this->startup->uuid}/builder/steps/{$submission->id}/submit")
            ->assertSessionHasErrors('builder');

        $this->actingAs($this->owner)->post("/workspace/startups/{$this->startup->uuid}/builder/steps/{$submission->id}/evidence", [
            'title' => 'Research notes', 'url' => 'https://example.org/research', 'source_note' => 'Interview summary source.',
        ])->assertRedirect();
        $this->assertDatabaseHas('venture_step_evidence', ['submission_id' => $submission->id, 'kind' => 'link']);
        $this->actingAs($this->owner)->post("/workspace/startups/{$this->startup->uuid}/builder/steps/{$submission->id}/submit")->assertRedirect();

        $reviewer = User::factory()->withRole('builder_reviewer')->create();
        $registry = app(RegistryService::class);
        $registry->acceptInvitation($registry->invite($this->startup, $this->owner, $reviewer->email, 'viewer'), $reviewer);
        $this->actingAs($this->owner)->post("/workspace/startups/{$this->startup->uuid}/builder/steps/{$submission->id}/review", [
            'decision' => 'validated', 'feedback' => 'A founder cannot act as reviewer.',
        ])->assertForbidden();
        $this->actingAs($reviewer)->post("/workspace/startups/{$this->startup->uuid}/builder/steps/{$submission->id}/review", [
            'decision' => 'changes_requested', 'feedback' => 'Please clarify the method behind this evidence.',
        ])->assertRedirect();
        $this->assertSame('changes_requested', $submission->fresh()->status);
        $this->actingAs($this->owner)->put("/workspace/startups/{$this->startup->uuid}/builder/steps/{$submission->id}", [
            'version' => $submission->fresh()->version, 'responses' => ['answer' => 'Revised evidence-backed response'],
            'checklist' => ['reviewed' => '1'],
        ])->assertRedirect();
        $this->actingAs($this->owner)->post("/workspace/startups/{$this->startup->uuid}/builder/steps/{$submission->id}/submit")->assertRedirect();
        $this->actingAs($reviewer)->post("/workspace/startups/{$this->startup->uuid}/builder/steps/{$submission->id}/review", [
            'decision' => 'validated', 'feedback' => 'Evidence and response reviewed and accepted.',
        ])->assertRedirect();
        $this->assertSame('validated', $submission->fresh()->status);
        $this->assertDatabaseHas('venture_step_revisions', ['submission_id' => $submission->id, 'reason' => 'validated']);
        $this->assertDatabaseHas('venture_step_reviews', ['submission_id' => $submission->id, 'decision' => 'validated']);
    }

    public function test_uploaded_evidence_is_private_to_startup_members(): void
    {
        Storage::fake('local');
        $this->firstStep->forceFill(['evidence_requirement' => 'optional'])->save();
        $this->actingAs($this->owner)->post("/workspace/startups/{$this->startup->uuid}/builder/start");
        $submission = VentureStepSubmission::query()->where('startup_uuid', $this->startup->uuid)->where('step_key', 'first_step')->firstOrFail();
        $this->actingAs($this->owner)->post("/workspace/startups/{$this->startup->uuid}/builder/steps/{$submission->id}/evidence", [
            'title' => 'Prototype image', 'file' => UploadedFile::fake()->image('prototype.png'),
        ])->assertRedirect();
        $evidence = $submission->evidence()->firstOrFail();
        Storage::disk('local')->assertExists($evidence->path);
        $download = $this->actingAs($this->owner)->get("/workspace/startups/{$this->startup->uuid}/builder/steps/{$submission->id}/evidence/{$evidence->id}");
        $download->assertOk();
        $this->assertStringContainsString('no-store', $download->headers->get('Cache-Control'));
        $outsider = User::factory()->withRole('startup_user')->create();
        $other = app(RegistryService::class)->createStartup($outsider, [
            'legal_name' => 'Other Builder Ltd', 'display_name' => 'Other Builder', 'registration_number' => null,
            'country' => 'UG', 'year_founded' => null, 'description' => null, 'website' => null,
        ], []);
        $this->actingAs($outsider)->get("/workspace/startups/{$other->uuid}/builder/steps/{$submission->id}/evidence/{$evidence->id}")->assertNotFound();
    }

    public function test_template_output_is_editable_versioned_and_exported(): void
    {
        $this->firstStep->forceFill(['output_template' => "# {{startup_name}}\n\n{{answer}}\n\n{{date}}"])->save();
        $this->actingAs($this->owner)->post("/workspace/startups/{$this->startup->uuid}/builder/start");
        $submission = VentureStepSubmission::query()->where('startup_uuid', $this->startup->uuid)->where('step_key', 'first_step')->firstOrFail();
        $this->actingAs($this->owner)->put("/workspace/startups/{$this->startup->uuid}/builder/steps/{$submission->id}", [
            'version' => 1, 'responses' => ['answer' => 'User provided plan'],
        ])->assertRedirect();
        $this->actingAs($this->owner)->post("/workspace/startups/{$this->startup->uuid}/builder/steps/{$submission->id}/document/generate")
            ->assertRedirect();
        $document = $submission->document()->firstOrFail();
        $first = $document->version();
        $this->assertSame('generated', $first->source);
        $this->assertStringContainsString('Builder Test', $first->body);
        $this->assertStringContainsString('User provided plan', $first->body);

        $this->actingAs($this->owner)->put("/workspace/startups/{$this->startup->uuid}/builder/steps/{$submission->id}/document/{$document->id}", [
            'expected_version' => 1, 'body' => "# Edited plan\n\nReviewed manually.",
        ])->assertRedirect();
        $document->refresh();
        $this->assertSame(2, $document->current_version);
        $this->assertSame('edited', $document->version()->source);
        $this->actingAs($this->owner)->get("/workspace/startups/{$this->startup->uuid}/builder/steps/{$submission->id}/document/{$document->id}/export")
            ->assertOk()->assertSeeText('Reviewed manually.')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($this->owner)->put("/workspace/startups/{$this->startup->uuid}/builder/steps/{$submission->id}/document/{$document->id}", [
            'expected_version' => 1, 'body' => 'Stale edit',
        ])->assertSessionHasErrors('document');
        $this->assertSame("# Edited plan\n\nReviewed manually.", $document->fresh()->version()->body);
    }
}
