<?php

namespace Tests\Feature\Growth;

use App\Growth\FrameworkDefinition;
use App\Growth\GrowthService;
use App\Growth\Scorer;
use App\Models\Builder\BuilderCurriculum;
use App\Models\Builder\BuilderModule;
use App\Models\Builder\BuilderStepDefinition;
use App\Models\Challenges\Challenge;
use App\Models\Challenges\ChallengeVersion;
use App\Models\Growth\GrowthAction;
use App\Models\Growth\VgiAssessment;
use App\Models\Growth\VgiFramework;
use App\Models\Knowledge\KnowledgeResource;
use App\Models\Registry\OutboxEvent;
use App\Models\Registry\Startup;
use App\Models\User;
use App\Registry\RegistryService;
use App\Support\Features;
use Database\Seeders\VgiStarterSeeder;
use Tests\TestCase;

class GrowthTest extends TestCase
{
    private User $owner;

    private Startup $startup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(VgiStarterSeeder::class);
        Features::set('knowledge', true, null);
        $this->owner = User::factory()->withRole('startup_user')->create();
        $this->startup = app(RegistryService::class)->createStartup($this->owner, [
            'legal_name' => 'Green Fields Ltd', 'display_name' => 'Green Fields', 'registration_number' => null,
            'country' => 'UG', 'year_founded' => 2023, 'description' => null, 'website' => null,
        ], []);
    }

    private function approveStarter(): VgiFramework
    {
        $framework = VgiFramework::query()->firstOrFail();
        app(GrowthService::class)->approve($framework, User::factory()->withRole('platform_admin')->create(), 'Reviewed by the programs team.');

        return $framework->fresh();
    }

    /** Answers that pick the given option position for every question. */
    private function answers(VgiFramework $framework, int $position): array
    {
        return array_map(fn ($q) => $q['options'][min($position, count($q['options']) - 1)]['key'], FrameworkDefinition::questions($framework->definition));
    }

    /** Evidence fixtures for required prompts whose selected answer expects evidence. */
    private function requiredEvidence(VgiFramework $framework, array $answers): array
    {
        $evidence = [];
        foreach (FrameworkDefinition::questions($framework->definition) as $key => $question) {
            $option = collect($question['options'])->firstWhere('key', $answers[$key] ?? null);
            if (($question['evidence'] ?? 'none') === 'required' && $option && Scorer::expectsEvidence($question, $option)) {
                $evidence[$key] = 'Fixture evidence reference for '.$key;
            }
        }

        return $evidence;
    }

    private function member(string $role): User
    {
        $user = User::factory()->withRole('startup_user')->create(['email' => $role.'@example.org']);
        $registry = app(RegistryService::class);
        $registry->acceptInvitation($registry->invite($this->startup, $this->owner, $role.'@example.org', $role), $user);

        return $user;
    }

    public function test_nothing_is_offered_until_a_rubric_is_approved(): void
    {
        $this->actingAs($this->owner)->get("/workspace/startups/{$this->startup->uuid}/growth")->assertOk()->assertSee('Coming soon');
        $this->actingAs($this->owner)->post("/workspace/startups/{$this->startup->uuid}/growth/assessments")->assertSessionHasErrors('growth');
        $this->assertSame(0, VgiAssessment::query()->count());
    }

    public function test_four_eyes_approval_and_frozen_versions(): void
    {
        $framework = VgiFramework::query()->firstOrFail();
        $editor = User::factory()->withRole('platform_admin')->create();
        $json = json_encode($framework->definition);

        $this->actingAs($editor)->put("/admin/vgi/frameworks/{$framework->id}", ['definition' => $json, 'description' => 'Edited'])->assertSessionHasNoErrors();
        $this->actingAs($editor)->post("/admin/vgi/frameworks/{$framework->id}/approve", ['note' => 'I reviewed my own work.'])->assertSessionHasErrors('approve');
        $this->assertSame('draft', $framework->fresh()->status);

        $this->actingAs(User::factory()->withRole('ecosystem_manager')->create())->post("/admin/vgi/frameworks/{$framework->id}/approve", ['note' => 'Looks fine to me.'])->assertForbidden();

        $approver = User::factory()->withRole('platform_admin')->create();
        $this->actingAs($approver)->post("/admin/vgi/frameworks/{$framework->id}/approve", ['note' => 'Reviewed with the programs team.'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $framework->fresh()->status);

        $this->actingAs($editor)->put("/admin/vgi/frameworks/{$framework->id}", ['definition' => $json])->assertSessionHasErrors('definition');
        $this->actingAs($editor)->put("/admin/vgi/frameworks/{$framework->id}", ['definition' => '{not json'])->assertSessionHasErrors('definition');
        $this->assertDatabaseHas('audit_logs', ['action' => 'vgi.framework_approved']);
    }

    public function test_invalid_rubrics_cannot_be_saved(): void
    {
        $framework = VgiFramework::query()->firstOrFail();
        $definition = $framework->definition;
        $definition['bands'][0]['max'] = 10;

        $this->actingAs(User::factory()->withRole('ecosystem_manager')->create())
            ->put("/admin/vgi/frameworks/{$framework->id}", ['definition' => json_encode($definition)])->assertSessionHasErrors('definition');
        $this->assertSame(24.9, (float) $framework->fresh()->definition['bands'][0]['max']);
    }

    public function test_founder_completes_an_assessment_with_reproducible_results(): void
    {
        $framework = $this->approveStarter();
        $base = "/workspace/startups/{$this->startup->uuid}/growth";
        $this->actingAs($this->owner)->post("$base/assessments")->assertRedirect();
        $assessment = VgiAssessment::query()->sole();

        $answers = $this->answers($framework, 1);
        $partial = array_slice($answers, 0, 5, true);
        $this->actingAs($this->owner)->put("$base/assessments/{$assessment->uuid}", ['answers' => $partial, 'intent' => 'save'])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->put("$base/assessments/{$assessment->uuid}", ['answers' => $partial, 'intent' => 'submit'])->assertSessionHasErrors('growth');
        $this->assertSame('draft', $assessment->fresh()->status);

        $evidence = $this->requiredEvidence($framework, $answers);
        $this->actingAs($this->owner)->put("$base/assessments/{$assessment->uuid}", ['answers' => $answers, 'evidence' => $evidence, 'intent' => 'submit'])->assertRedirect();
        $assessment->refresh();
        $this->assertSame('submitted', $assessment->status);
        $this->assertNotNull($assessment->score);
        $this->assertNotNull($assessment->band_label);
        $this->assertTrue(app(GrowthService::class)->verify($assessment));
        $this->assertTrue(OutboxEvent::query()->where('type', 'nsp.assessment.completed.v1')->exists());
        $this->assertTrue(OutboxEvent::query()->where('type', 'nsp.growth.stage.changed.v1')->exists());

        $curriculum = BuilderCurriculum::query()->create(['name' => 'Provisional', 'version' => 1, 'status' => 'published', 'methodology_status' => 'provisional']);
        $module = BuilderModule::query()->create(['curriculum_id' => $curriculum->id, 'position' => 1, 'key' => 'problem_module', 'title' => 'Problem work']);
        BuilderStepDefinition::query()->create([
            'curriculum_id' => $curriculum->id, 'module_id' => $module->id, 'position' => 1, 'key' => 'problem_step',
            'title' => 'Clarify the problem', 'fields' => [['key' => 'statement', 'label' => 'Statement', 'type' => 'text']],
            'vgi_dimension_keys' => ['problem_clarity'],
        ]);
        $knowledge = KnowledgeResource::query()->create([
            'slug' => 'problem-framing-guide', 'title' => 'Problem framing guide', 'summary' => 'An evidence-based way to define a problem.',
            'body' => 'Test guide body.', 'resource_type' => 'guide', 'license' => 'cc_by', 'visibility' => 'startups',
            'audience' => ['founders'], 'vgi_dimension_keys' => ['problem_clarity'], 'source' => 'Synthetic test source',
        ]);
        $knowledge->forceFill(['status' => 'published', 'published_at' => now(), 'current_version' => 4])->save();
        Features::set('challenges', true, null);
        $challenge = Challenge::query()->create([
            'title' => 'Improve local food storage', 'problem_description' => 'A published challenge about post-harvest storage.',
            'sector' => 'Agriculture & Agritech', 'priority' => 'high', 'visibility' => 'public',
            'vgi_dimension_keys' => ['problem_clarity'],
        ]);
        $publishedChallengeVersion = ChallengeVersion::query()->create([
            'challenge_id' => $challenge->id, 'version' => 1, 'title' => 'Improve local food storage',
            'problem_description' => 'A published challenge about post-harvest storage.', 'priority' => 'high',
            'visibility' => 'public', 'content' => ['title' => 'Improve local food storage', 'vgi_dimension_keys' => ['problem_clarity'], 'visibility' => 'public'],
            'published_at' => now(),
        ]);
        $challenge->forceFill([
            'status' => 'published', 'published_version_id' => $publishedChallengeVersion->id, 'published_at' => now(),
        ])->save();
        // The working copy may have changed; guidance must still match the public immutable snapshot.
        $challenge->forceFill(['vgi_dimension_keys' => ['team']])->save();
        Challenge::query()->create([
            'title' => 'Draft storage challenge', 'problem_description' => 'Draft content must not be recommended.',
            'sector' => 'Agriculture & Agritech', 'vgi_dimension_keys' => ['problem_clarity'],
        ]);
        $restricted = KnowledgeResource::query()->create([
            'slug' => 'restricted-problem-guide', 'title' => 'Restricted problem guide', 'resource_type' => 'guide',
            'license' => 'all_rights_reserved', 'visibility' => 'staff', 'vgi_dimension_keys' => ['problem_clarity'],
        ]);
        $restricted->forceFill(['status' => 'published', 'published_at' => now()])->save();
        $this->actingAs($this->owner)->get("$base/assessments/{$assessment->uuid}")->assertOk()
            ->assertSee($assessment->band_label)->assertSee('self-assessment')->assertSee('Add to growth plan')
            ->assertSee(route('workspace.builder', $this->startup).'#step-problem_step')
            ->assertSee('Accept suggestion')->assertSee('Dismiss with reason')
            ->assertSee('Problem framing guide')->assertSee('Synthetic test source')->assertDontSee('Restricted problem guide')
            ->assertSee('published version 4')->assertSee('published version 1')
            ->assertSee('Improve local food storage')->assertDontSee('Draft storage challenge');
        $dimension = $assessment->result['gaps'][0]['dimension_key'];
        $feedbackUrl = "$base/assessments/{$assessment->uuid}/guidance/$dimension/feedback";
        $this->actingAs($this->owner)->post($feedbackUrl, ['feedback' => 'dismissed'])->assertSessionHasErrors('explanation');
        $this->actingAs($this->owner)->post($feedbackUrl, ['feedback' => 'dismissed', 'explanation' => 'Not relevant this quarter.'])->assertRedirect();
        $this->assertDatabaseHas('vgi_guidance_feedback', [
            'startup_uuid' => $this->startup->uuid, 'assessment_uuid' => $assessment->uuid,
            'user_id' => $this->owner->id, 'dimension_key' => $dimension, 'feedback' => 'dismissed',
            'explanation' => 'Not relevant this quarter.',
        ]);
        $this->actingAs($this->owner)->post($feedbackUrl, ['feedback' => 'accepted'])->assertRedirect();
        $this->assertDatabaseHas('vgi_guidance_feedback', [
            'assessment_uuid' => $assessment->uuid, 'user_id' => $this->owner->id,
            'dimension_key' => $dimension, 'feedback' => 'accepted', 'explanation' => null,
        ]);
        $stranger = User::factory()->withRole('startup_user')->create();
        $this->actingAs($stranger)->post($feedbackUrl, ['feedback' => 'accepted'])->assertNotFound();
        $this->actingAs($this->owner)->get("$base/assessments/{$assessment->uuid}/report")->assertOk()->assertSee('Priorities');
        // A submitted assessment cannot be changed.
        $this->actingAs($this->owner)->put("$base/assessments/{$assessment->uuid}", ['answers' => $this->answers($framework, 3), 'intent' => 'submit'])->assertSessionHasErrors('growth');
        $this->assertSame($assessment->score, $assessment->fresh()->score);
    }

    public function test_roles_and_tenant_isolation(): void
    {
        $framework = $this->approveStarter();
        $assessment = app(GrowthService::class)->start($this->startup, $this->owner);
        $answers = $this->answers($framework, 2);
        app(GrowthService::class)->saveAnswers($assessment, $this->owner, $answers, $this->requiredEvidence($framework, $answers));
        app(GrowthService::class)->submit($assessment, $this->owner);
        $base = "/workspace/startups/{$this->startup->uuid}/growth";

        $viewer = $this->member('viewer');
        $this->actingAs($viewer)->get($base)->assertOk()->assertSee('Owners, founders and admins fill in the assessment.');
        $this->actingAs($viewer)->post("$base/assessments")->assertForbidden();
        $this->actingAs($viewer)->get("$base/assessments/{$assessment->uuid}")->assertOk();

        $stranger = User::factory()->withRole('startup_user')->create();
        foreach (['', "/assessments/{$assessment->uuid}", "/assessments/{$assessment->uuid}/report"] as $path) {
            $this->actingAs($stranger)->get($base.$path)->assertNotFound();
        }

        // Another startup's assessment cannot be opened through this startup's address.
        $other = app(RegistryService::class)->createStartup($stranger, ['legal_name' => 'Other Co', 'display_name' => 'Other', 'registration_number' => null,
            'country' => 'UG', 'year_founded' => null, 'description' => null, 'website' => null], []);
        $theirs = app(GrowthService::class)->start($other, $stranger);
        $this->actingAs($this->owner)->get("$base/assessments/{$theirs->uuid}")->assertNotFound();
    }

    public function test_dimension_trends_compare_only_assessments_on_same_rubric_version(): void
    {
        $framework = $this->approveStarter();
        $service = app(GrowthService::class);
        foreach ([0, 3] as $index => $position) {
            if ($index > 0) {
                $this->travel(1)->seconds();
            }
            $assessment = $service->start($this->startup, $this->owner);
            $answers = $this->answers($framework, $position);
            $service->saveAnswers($assessment, $this->owner, $answers, $this->requiredEvidence($framework, $answers));
            $service->submit($assessment, $this->owner);
        }

        $this->actingAs($this->owner)->get("/workspace/startups/{$this->startup->uuid}/growth")
            ->assertOk()->assertSee('Dimension-level progress')->assertSee('Previous comparable')
            ->assertSee('Increased');

        $editor = User::factory()->withRole('ecosystem_manager')->create();
        $newFramework = $service->createDraft($editor, $framework);
        $definition = $newFramework->definition;
        $service->updateDraft($newFramework, $editor, $definition, 'New rubric revision');
        $service->approve($newFramework, User::factory()->withRole('platform_admin')->create(), 'Reviewed a new version of the rubric.');
        $assessment = $service->start($this->startup, $this->owner);
        $answers = $this->answers($newFramework, 2);
        $service->saveAnswers($assessment, $this->owner, $answers, $this->requiredEvidence($newFramework, $answers));
        $service->submit($assessment, $this->owner);

        $this->actingAs($this->owner)->get("/workspace/startups/{$this->startup->uuid}/growth")
            ->assertOk()->assertSee('Dimension-level progress')->assertSee('Not comparable');
    }

    public function test_growth_plan_from_gaps_and_progress_updates(): void
    {
        $framework = $this->approveStarter();
        $service = app(GrowthService::class);
        $assessment = $service->start($this->startup, $this->owner);
        $answers = $this->answers($framework, 0);
        $service->saveAnswers($assessment, $this->owner, $answers, $this->requiredEvidence($framework, $answers));
        $service->submit($assessment, $this->owner);
        $gap = $assessment->fresh()->result['gaps'][0];
        $base = "/workspace/startups/{$this->startup->uuid}/growth";

        $this->actingAs($this->owner)->post("$base/actions", ['assessment_uuid' => $assessment->uuid, 'question_key' => $gap['question_key']])->assertRedirect();
        $action = GrowthAction::query()->sole();
        $this->assertSame($gap['tip'], $action->title);
        $this->actingAs($this->owner)->get($base)->assertSee($gap['tip']);
        $this->actingAs($this->owner)->get("$base/assessments/{$assessment->uuid}")->assertSee('In your growth plan');

        // Owners must be on the team.
        $stranger = User::factory()->create();
        $this->actingAs($this->owner)->post("$base/actions", ['title' => 'Hire a CFO', 'owner_user_id' => $stranger->id])->assertRedirect();
        $this->assertNull(GrowthAction::query()->where('title', 'Hire a CFO')->value('owner_user_id'));

        $member = $this->member('team_member');
        $this->actingAs($member)->put("$base/actions/{$action->id}", ['status' => 'done', 'note' => 'Signed the agreement today'])->assertRedirect();
        $this->assertSame('done', $action->fresh()->status);
        $this->assertNotNull($action->fresh()->completed_at);
        $this->assertDatabaseHas('growth_updates', ['growth_action_id' => $action->id, 'note' => 'Signed the agreement today']);
        $this->actingAs($member)->delete("$base/actions/{$action->id}")->assertForbidden();
    }

    public function test_staff_adjustment_needs_a_reason_and_is_shown_to_founders(): void
    {
        $framework = $this->approveStarter();
        $service = app(GrowthService::class);
        $assessment = $service->start($this->startup, $this->owner);
        $answers = $this->answers($framework, 1);
        $service->saveAnswers($assessment, $this->owner, $answers, $this->requiredEvidence($framework, $answers));
        $service->submit($assessment, $this->owner);
        $calculatedScore = $assessment->fresh()->score;
        $staff = User::factory()->withRole('ecosystem_manager')->create();

        $this->actingAs($staff)->get("/admin/vgi/assessments/{$assessment->uuid}")->assertOk()->assertSee('matches the saved result');
        $this->actingAs($staff)->post("/admin/vgi/assessments/{$assessment->uuid}/override", ['override_score' => 50, 'reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($staff)->post("/admin/vgi/assessments/{$assessment->uuid}/override", ['override_score' => 50, 'override_band' => 'Early traction', 'reason' => 'Verified signed customer contracts during the site visit.'])->assertSessionHasNoErrors();

        $assessment->refresh();
        $this->assertSame(50.0, $assessment->displayScore());
        $this->assertSame($calculatedScore, $assessment->score);
        $this->actingAs($this->owner)->get("/workspace/startups/{$this->startup->uuid}/growth")
            ->assertSee('Verified signed customer contracts')->assertSee('Early traction')->assertSee((string) $calculatedScore);
        $this->assertDatabaseHas('audit_logs', ['action' => 'vgi.assessment_overridden', 'actor_id' => $staff->id]);
        $this->actingAs(User::factory()->withRole('content_editor')->create())->get("/admin/vgi/assessments/{$assessment->uuid}")->assertForbidden();
    }

    public function test_new_version_replaces_old_for_new_assessments_only(): void
    {
        $v1 = $this->approveStarter();
        $service = app(GrowthService::class);
        $old = $service->start($this->startup, $this->owner);
        $answers = $this->answers($v1, 1);
        $service->saveAnswers($old, $this->owner, $answers, $this->requiredEvidence($v1, $answers));
        $service->submit($old, $this->owner);

        $editor = User::factory()->withRole('ecosystem_manager')->create();
        $v2 = $service->createDraft($editor);
        $definition = $v2->definition;
        $definition['dimensions'][0]['weight'] = 50;
        $service->updateDraft($v2, $editor, $definition, 'Team weighted more');
        $service->approve($v2, User::factory()->withRole('platform_admin')->create(), 'Reviewed new weights with programs.');

        $this->assertSame('retired', $v1->fresh()->status);
        $this->assertSame($v2->id, VgiFramework::current()->id);
        $this->assertSame($v1->id, $old->fresh()->framework_id);
        $this->assertTrue($service->verify($old->fresh()));
        $this->assertSame($v2->id, $service->start($this->startup, $this->owner)->framework_id);
    }

    public function test_growth_is_hidden_when_the_feature_is_off(): void
    {
        Features::set('vgi', false, null);

        $this->actingAs($this->owner)->get("/workspace/startups/{$this->startup->uuid}/growth")->assertNotFound();
        $this->actingAs(User::factory()->withRole('platform_admin')->create())->get('/admin/vgi')->assertNotFound();
    }
}
