<?php

namespace Tests\Feature\Knowledge;

use App\Models\Knowledge\KnowledgeResource;
use App\Models\Registry\StartupMembership;
use App\Models\User;
use App\Registry\RegistryService;
use App\Support\Features;
use Tests\TestCase;

class KnowledgeSearchAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Features::set('knowledge', true, null);
    }

    private function resource(string $slug, string $visibility, array $overrides = []): KnowledgeResource
    {
        $resource = KnowledgeResource::query()->create(array_merge([
            'slug' => $slug, 'title' => 'Water systems guide '.$slug, 'summary' => 'Water operations reference.',
            'body' => 'Detailed water systems material.', 'resource_type' => 'practical_guide',
            'license' => 'cc-by', 'visibility' => $visibility,
        ], $overrides));
        $resource->forceFill(['status' => $overrides['status'] ?? 'published', 'published_at' => now(), 'current_version' => 2])->save();

        return $resource;
    }

    public function test_keyword_fallback_search_respects_live_and_visibility_boundaries(): void
    {
        config(['knowledge.semantic.enabled' => true, 'knowledge.semantic.ranker' => null]);
        $public = $this->resource('water-public', 'public');
        $memberOnly = $this->resource('water-members', 'members');
        $startupOnly = $this->resource('water-startups', 'startups');
        $this->resource('water-staff', 'staff');
        $this->resource('water-draft', 'public', ['status' => 'draft']);
        $this->resource('water-expired', 'public', ['expires_at' => now()->subMinute()]);

        $this->get('/knowledge?q=water')->assertOk()->assertSee('Search method: Keyword')
            ->assertSee($public->title)->assertDontSee($memberOnly->title)->assertDontSee($startupOnly->title)
            ->assertDontSee('water-staff')->assertDontSee('water-draft')->assertDontSee('water-expired');

        $member = User::factory()->create();
        $this->actingAs($member)->get('/knowledge?q=water')->assertOk()
            ->assertSee($public->title)->assertSee($memberOnly->title)->assertDontSee($startupOnly->title)
            ->assertDontSee('water-staff');

        $founder = User::factory()->withRole('startup_user')->create();
        $startup = app(RegistryService::class)->createStartup($founder, [
            'legal_name' => 'Water Startup Ltd', 'display_name' => 'Water Startup', 'registration_number' => 'UG-WSEARCH',
            'country' => 'UG', 'year_founded' => 2024, 'description' => null, 'website' => null,
        ], []);
        StartupMembership::query()->where('startup_uuid', $startup->uuid)->where('user_id', $founder->id)->firstOrFail();
        $this->actingAs($founder)->get('/knowledge?q=water')->assertOk()
            ->assertSee($public->title)->assertSee($memberOnly->title)->assertSee($startupOnly->title)
            ->assertDontSee('water-staff')->assertDontSee('water-expired');
    }

    public function test_resource_page_and_saved_resources_render_with_provenance(): void
    {
        $user = User::factory()->create();
        $resource = $this->resource('water-source', 'public', [
            'source' => 'Ministry of Water', 'publisher' => 'Public Research Office', 'license' => 'cc0',
            'body' => 'A **checked** source text.',
        ]);

        $this->actingAs($user)->get(route('knowledge.show', $resource))->assertOk()
            ->assertSee('Ministry of Water')->assertSee('version 2')->assertSee('A <strong>checked</strong> source text.', false);
        $this->from(route('knowledge.show', $resource))->actingAs($user)
            ->post(route('knowledge.bookmark', $resource))->assertRedirect();
        $this->actingAs($user)->get(route('knowledge.mine'))->assertOk()->assertSee('Water systems guide water-source');
    }
}
