<?php

namespace Tests\Feature\Registry;

use App\Models\Registry\DuplicateCandidate;
use App\Models\Registry\Startup;
use App\Models\Registry\StartupAlias;
use App\Models\Registry\StartupMerge;
use App\Models\User;
use App\Registry\RegistryService;
use Tests\TestCase;

class DuplicateReviewTest extends TestCase
{
    /** Imported/legacy records can bypass the sign-up checks, so the scan must catch them. */
    private function legacyStartup(User $owner, array $attributes): Startup
    {
        $startup = new Startup(array_merge(['country' => 'UG'], $attributes));
        $startup->created_by = $owner->id;
        $startup->status = 'active';
        $startup->save();
        $startup->memberships()->create(['user_id' => $owner->id, 'role' => 'owner', 'status' => 'active', 'accepted_at' => now()]);

        return $startup;
    }

    public function test_scan_scores_registration_and_website_above_names(): void
    {
        $a = $this->legacyStartup(User::factory()->create(), ['legal_name' => 'Kampala Bikes Ltd', 'display_name' => 'Kampala Bikes', 'registration_number' => 'UG-123']);
        $b = $this->legacyStartup(User::factory()->create(), ['legal_name' => 'KB Mobility', 'display_name' => 'KB', 'registration_number' => 'ug 123']);
        $c = $this->legacyStartup(User::factory()->create(), ['legal_name' => 'Sunny Foods', 'display_name' => 'Sunny Foods']);
        $d = $this->legacyStartup(User::factory()->create(), ['legal_name' => 'Sunny Foods Limited', 'display_name' => 'Sunny Foods']);

        $this->assertSame(2, app(RegistryService::class)->scanForDuplicates());
        $this->assertSame(0, app(RegistryService::class)->scanForDuplicates(), 'A second scan must not duplicate candidates');

        $strong = DuplicateCandidate::query()->where('evidence', 'like', '%registration%')->firstOrFail();
        $weak = DuplicateCandidate::query()->where('evidence', 'like', '%Same name%')->firstOrFail();
        $this->assertSame(90, $strong->score);
        $this->assertSame(30, $weak->score);
        // Nothing is merged automatically.
        $this->assertSame(4, Startup::query()->where('status', 'active')->count());
    }

    public function test_reviewer_merges_with_evidence_and_can_undo(): void
    {
        $reviewer = User::factory()->withRole('ecosystem_manager')->create();
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();
        $shared = User::factory()->create();
        $a = $this->legacyStartup($ownerA, ['legal_name' => 'Kampala Bikes Ltd', 'display_name' => 'Kampala Bikes', 'registration_number' => 'UG-123']);
        $b = $this->legacyStartup($ownerB, ['legal_name' => 'KB Mobility', 'display_name' => 'KB', 'registration_number' => 'UG123']);
        // The same person is on both teams: after the merge they must not appear twice.
        $a->memberships()->create(['user_id' => $shared->id, 'role' => 'team_member', 'status' => 'active']);
        $b->memberships()->create(['user_id' => $shared->id, 'role' => 'viewer', 'status' => 'active']);
        StartupAlias::query()->create(['source_system' => 'ims', 'external_id' => 'APP-77', 'startup_uuid' => $b->uuid]);
        app(RegistryService::class)->scanForDuplicates();
        $candidate = DuplicateCandidate::query()->firstOrFail();

        $this->actingAs($reviewer)->post("/admin/duplicates/{$candidate->id}", ['decision' => 'merge', 'keep' => $a->uuid, 'notes' => ''])
            ->assertSessionHasErrors('notes');
        $this->actingAs($reviewer)->post("/admin/duplicates/{$candidate->id}", ['decision' => 'merge', 'keep' => $a->uuid, 'notes' => 'Confirmed by phone with both founders.'])
            ->assertSessionHasNoErrors();

        $this->assertSame('merged', $b->fresh()->status);
        $this->assertSame($a->uuid, $b->fresh()->merged_into_uuid);
        $this->assertNotNull($a->membershipOf($ownerB));
        $this->assertSame(1, $a->memberships()->where('user_id', $shared->id)->where('status', 'active')->count());
        $this->assertSame($a->uuid, StartupAlias::query()->where('external_id', 'APP-77')->value('startup_uuid'));
        $this->assertSame($a->uuid, StartupAlias::query()->where('source_system', 'registry')->where('external_id', $b->uuid)->value('startup_uuid'));
        // The merged record's old team can no longer open it.
        $this->actingAs($ownerB)->get("/workspace/startups/{$b->uuid}")->assertNotFound();

        $merge = StartupMerge::query()->firstOrFail();
        $this->actingAs($reviewer)->post("/admin/merges/{$merge->id}/undo")->assertSessionHasNoErrors();

        $this->assertSame('active', $b->fresh()->status);
        $this->assertNotNull($b->membershipOf($ownerB));
        $this->assertNull($a->membershipOf($ownerB));
        $this->assertNotNull($b->membershipOf($shared));
        $this->assertSame($b->uuid, StartupAlias::query()->where('external_id', 'APP-77')->value('startup_uuid'));
        $this->assertFalse(StartupAlias::query()->where('source_system', 'registry')->exists());
        $this->assertSame('pending', $candidate->fresh()->status);
        $this->actingAs($reviewer)->post("/admin/merges/{$merge->id}/undo")->assertSessionHasErrors('merge');
    }

    public function test_reviewer_can_mark_a_pair_as_different_companies(): void
    {
        $reviewer = User::factory()->withRole('ecosystem_manager')->create();
        $this->legacyStartup(User::factory()->create(), ['legal_name' => 'Sunny Foods', 'display_name' => 'Sunny Foods']);
        $this->legacyStartup(User::factory()->create(), ['legal_name' => 'Sunny Foods Ltd', 'display_name' => 'Sunny Foods']);
        app(RegistryService::class)->scanForDuplicates();
        $candidate = DuplicateCandidate::query()->firstOrFail();

        $this->actingAs($reviewer)->post("/admin/duplicates/{$candidate->id}", ['decision' => 'not_duplicate', 'notes' => 'Different districts and owners.'])->assertSessionHasNoErrors();

        $this->assertSame('not_duplicate', $candidate->fresh()->status);
        $this->assertSame(2, Startup::query()->where('status', 'active')->count());
    }

    public function test_only_permitted_staff_reach_registry_admin_pages(): void
    {
        $startup = $this->legacyStartup(User::factory()->create(), ['legal_name' => 'X', 'display_name' => 'X']);

        $this->actingAs(User::factory()->withRole('content_editor')->create())->get('/admin/startups')->assertForbidden();
        $this->actingAs(User::factory()->withRole('content_editor')->create())->get('/admin/duplicates')->assertForbidden();
        $manager = User::factory()->withRole('ecosystem_manager')->create();
        $this->actingAs($manager)->get('/admin/startups')->assertOk()->assertSee('X');
        $this->actingAs($manager)->get("/admin/startups/{$startup->uuid}")->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'registry.staff_viewed_startup', 'actor_id' => $manager->id]);
        $this->actingAs($manager)->get('/admin/duplicates')->assertOk();
    }

    public function test_staff_see_private_figures_only_when_the_startup_allows_it(): void
    {
        $owner = User::factory()->withRole('startup_user')->create();
        $startup = app(RegistryService::class)->createStartup($owner, [
            'legal_name' => 'Money Co', 'display_name' => 'Money Co', 'registration_number' => null, 'country' => 'UG',
            'year_founded' => 2024, 'description' => null, 'website' => null,
        ], ['traction_summary' => 'Secret traction', 'visibility' => ['traction' => 'members']]);
        $manager = User::factory()->withRole('ecosystem_manager')->create();

        $this->actingAs($manager)->get("/admin/startups/{$startup->uuid}")->assertDontSee('Secret traction');
        $startup->profile->update(['visibility' => ['traction' => 'ecosystem']]);
        $this->actingAs($manager)->get("/admin/startups/{$startup->uuid}")->assertSee('Secret traction');
    }
}
