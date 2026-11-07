<?php

namespace Tests\Feature\Challenges;

use App\Challenges\ChallengeWorkflow;
use App\Models\Challenges\Challenge;
use App\Models\Challenges\ChallengeEvidence;
use App\Models\Challenges\ChallengeSolutionSubmission;
use App\Models\User;
use App\Support\Features;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ChallengeWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Features::set('challenges', true, null);
    }

    private function content(string $title): array
    {
        return [
            'title' => $title,
            'problem_description' => 'A sufficiently clear description of the challenge problem.',
            'sector' => 'Agriculture & Agritech', 'country' => 'UG', 'priority' => 'high',
            'category' => 'innovation', 'visibility' => 'public', 'vgi_dimension_keys' => ['problem_clarity'],
            'research_references' => [['title' => 'Test source', 'url' => 'https://example.org/source', 'accessed' => '2026-11-07']],
        ];
    }

    private function publishChallenge(string $title, string $visibility = 'public', string $sector = 'Agriculture & Agritech'): Challenge
    {
        $submitter = User::factory()->withRole('startup_user')->create();
        $reviewer = User::factory()->withRole('platform_admin')->create();
        $workflow = app(ChallengeWorkflow::class);
        $content = array_merge($this->content($title), ['visibility' => $visibility, 'sector' => $sector]);
        $challenge = $workflow->saveContent(new Challenge, $content, $submitter);
        $workflow->submit($challenge, $submitter);
        $workflow->review($challenge->fresh(), $reviewer, 'approve', 'Reviewed sources and relevance.', null, 'Reference reviewed.');
        $workflow->publish($challenge->fresh(), $reviewer);

        return $challenge->fresh();
    }

    public function test_only_approved_published_snapshots_are_public_and_revisions_do_not_replace_live_copy_early(): void
    {
        $submitter = User::factory()->withRole('startup_user')->create();
        $reviewer = User::factory()->withRole('platform_admin')->create();
        $workflow = app(ChallengeWorkflow::class);
        $challenge = $workflow->saveContent(new Challenge, $this->content('Local storage challenge'), $submitter);

        $this->get('/challenges')->assertOk()->assertDontSee('Local storage challenge');
        $this->actingAs($submitter)->get(route('challenges.show', $challenge))->assertNotFound();

        $submitted = $workflow->submit($challenge, $submitter);
        $this->assertSame(1, $submitted->version);
        $this->get('/challenges')->assertDontSee('Local storage challenge');
        $workflow->review($challenge->fresh(), $reviewer, 'approve', 'Sources and clarity checked.', null, 'Reviewed cited source.');
        $this->get('/challenges')->assertDontSee('Local storage challenge');

        $workflow->publish($challenge->fresh(), $reviewer);
        $this->get('/challenges')->assertSee('Local storage challenge');
        $this->actingAs($submitter)->get(route('challenges.show', $challenge))->assertOk()
            ->assertSee('Version 1')->assertSee('Provenance')->assertSee('Test source');

        $workflow->saveContent($challenge->fresh(), $this->content('Revised storage challenge'), $submitter);
        $this->assertSame('draft', $challenge->fresh()->status);
        $this->get('/challenges')->assertSee('Local storage challenge')->assertDontSee('Revised storage challenge');

        $newVersion = $workflow->submit($challenge->fresh(), $submitter);
        $workflow->review($challenge->fresh(), $reviewer, 'approve', 'Revised source checked.', null, 'Reviewed the revision.');
        $this->get('/challenges')->assertSee('Local storage challenge')->assertDontSee('Revised storage challenge');
        $workflow->publish($challenge->fresh(), $reviewer);
        $this->get('/challenges')->assertSee('Revised storage challenge')->assertDontSee('Local storage challenge');
        $this->assertSame(2, $newVersion->version);
        $this->assertDatabaseHas('challenge_versions', ['id' => $submitted->id, 'title' => 'Local storage challenge']);

        $workflow->archive($challenge->fresh(), $reviewer);
        $this->get('/challenges')->assertDontSee('Revised storage challenge');
        $this->get(route('challenges.show', $challenge))->assertNotFound();
    }

    public function test_rejections_need_reason_and_unpublished_work_cannot_be_published(): void
    {
        $submitter = User::factory()->withRole('startup_user')->create();
        $reviewer = User::factory()->withRole('platform_admin')->create();
        $workflow = app(ChallengeWorkflow::class);
        $challenge = $workflow->saveContent(new Challenge, $this->content('Water access challenge'), $submitter);

        try {
            $workflow->publish($challenge, $reviewer);
            $this->fail('A draft challenge cannot be published.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $workflow->submit($challenge, $submitter);
        try {
            $workflow->review($challenge->fresh(), $reviewer, 'reject', 'Not enough evidence.', null);
            $this->fail('A rejection requires a configured reason.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reason', $exception->errors());
        }

        $workflow->review($challenge->fresh(), $reviewer, 'reject', 'Not enough evidence.', array_key_first(config('challenges.rejection_reasons')));
        $this->assertSame('rejected', $challenge->fresh()->status);
        $this->get('/challenges')->assertDontSee('Water access challenge');
    }

    public function test_challenge_staff_permissions_are_separate_from_review_and_publish_rights(): void
    {
        $editor = User::factory()->withRole('content_editor')->create();
        $challenge = app(ChallengeWorkflow::class)->saveContent(new Challenge, $this->content('Editor challenge'), $editor);

        $this->actingAs($editor)->get('/admin/challenges')->assertOk()->assertSee('Challenge Exchange');
        $this->actingAs($editor)->get(route('admin.challenges.create'))->assertOk()->assertSee('New challenge');
        $this->actingAs($editor)->get(route('admin.challenges.show', $challenge))->assertOk()->assertSee('Editor challenge');
        $this->actingAs($editor)->get(route('admin.challenges.edit', $challenge))->assertOk()->assertSee('Problem description');
        app(ChallengeWorkflow::class)->submit($challenge->fresh(), $editor);
        $this->actingAs($editor)->post(route('admin.challenges.review', $challenge), [
            'decision' => 'approve', 'comments' => 'Looks clear.',
        ])->assertForbidden();
        $reviewer = User::factory()->withRole('platform_admin')->create();
        $this->actingAs($reviewer)->post(route('admin.challenges.review', $challenge), [
            'decision' => 'approve', 'comments' => 'Reviewed the submission.', 'source_validation_notes' => 'Source metadata checked.',
        ])->assertRedirect();
        $this->actingAs($reviewer)->post(route('admin.challenges.publish', $challenge))->assertRedirect();
        $this->get('/challenges')->assertSee('Editor challenge');
        $this->actingAs($reviewer)->get(route('admin.challenges.proposals', $challenge))->assertOk()->assertSee('Proposals');
        $this->actingAs(User::factory()->create())->get('/admin/challenges')->assertForbidden();
    }

    public function test_search_filters_only_published_visible_challenge_snapshots(): void
    {
        $public = $this->publishChallenge('Food storage resilience', 'public', 'Agriculture & Agritech');
        $members = $this->publishChallenge('Food transport network', 'members', 'Transport & Logistics');

        $this->get('/challenges?q=Food&sector=Agriculture+%26+Agritech')->assertOk()
            ->assertSee('Food storage resilience')->assertDontSee('Food transport network');
        $this->get(route('challenges.show', $members))->assertNotFound();

        $viewer = User::factory()->create();
        $this->actingAs($viewer)->get('/challenges?q=Food')->assertOk()
            ->assertSee('Food storage resilience')->assertSee('Food transport network');
        $this->assertNotSame($public->uuid, $members->uuid);
    }

    public function test_evidence_validation_and_bookmark_toggle_preserve_public_boundaries(): void
    {
        $submitter = User::factory()->withRole('startup_user')->create();
        $reviewer = User::factory()->withRole('platform_admin')->create();
        $workflow = app(ChallengeWorkflow::class);
        $challenge = $workflow->saveContent(new Challenge, $this->content('Cold chain challenge'), $submitter);

        $this->actingAs($submitter)->post(route('challenges.evidence.store', $challenge), [
            'source_title' => 'Agriculture research note',
            'evidence_type' => array_key_first(config('challenges.evidence_types')),
            'url' => 'https://example.org/research',
        ])->assertRedirect();
        $evidence = ChallengeEvidence::query()->sole();
        $this->assertFalse($evidence->validated);

        $workflow->submit($challenge->fresh(), $submitter);
        $workflow->review($challenge->fresh(), $reviewer, 'approve', 'Clear problem and source.', null, 'Source not yet independently validated.');
        $workflow->publish($challenge->fresh(), $reviewer);
        $this->get(route('challenges.show', $challenge))->assertOk()
            ->assertSee('Agriculture research note')->assertSee('Not yet reviewed');

        $this->actingAs($reviewer)->post(route('admin.challenges.evidence.validate', [$challenge, $evidence]), [
            'validated' => true,
            'validation_note' => 'Internal validation detail; do not disclose publicly.',
        ])->assertRedirect();
        $this->get(route('challenges.show', $challenge))->assertOk()
            ->assertSee('Evidence reviewed on')->assertDontSee('Internal validation detail');

        $this->actingAs($submitter)->post(route('challenges.bookmark', $challenge))->assertRedirect();
        $this->assertDatabaseHas('challenge_bookmarks', ['user_id' => $submitter->id, 'challenge_id' => $challenge->id]);
        $this->actingAs($submitter)->post(route('challenges.bookmark', $challenge))->assertRedirect();
        $this->assertDatabaseMissing('challenge_bookmarks', ['user_id' => $submitter->id, 'challenge_id' => $challenge->id]);
    }

    public function test_proposals_are_private_and_reviewers_can_update_status(): void
    {
        $challenge = $this->publishChallenge('Community water monitoring');
        $submitter = User::factory()->withRole('startup_user')->create();
        $this->actingAs($submitter)->post(route('challenges.propose', $challenge), [
            'summary' => 'We can deploy local water testing sensors with community partners.',
            'approach' => 'Work with existing community water committees.',
            'confirm' => '1',
        ])->assertRedirect();
        $proposal = ChallengeSolutionSubmission::query()->sole();

        $this->actingAs($submitter)->get(route('challenges.proposals.show', $proposal))->assertOk()
            ->assertSee('local water testing sensors');
        $this->actingAs(User::factory()->create())->get(route('challenges.proposals.show', $proposal))->assertNotFound();

        $reviewer = User::factory()->withRole('platform_admin')->create();
        $this->actingAs($reviewer)->get(route('challenges.proposals.show', $proposal))->assertOk();
        $this->actingAs($reviewer)->put(route('admin.challenges.proposals.update', [$challenge, $proposal]), ['status' => 'shortlisted'])
            ->assertRedirect();
        $this->assertSame('shortlisted', $proposal->fresh()->status);
    }
}
