<?php

namespace Tests\Feature\Knowledge;

use App\Models\Knowledge\KnowledgeCategory;
use App\Models\Knowledge\KnowledgeResource;
use App\Models\Knowledge\KnowledgeResourceVersion;
use App\Models\User;
use App\Support\Features;
use Tests\TestCase;

class KnowledgeEditorialTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Features::set('knowledge', true, null);
    }

    public function test_editor_queue_form_submit_and_four_eyes_publish_history_render(): void
    {
        $editor = User::factory()->withRole('platform_admin')->create();
        $reviewer = User::factory()->withRole('platform_admin')->create();

        $this->actingAs($editor)->get(route('admin.knowledge.index'))->assertOk()->assertSee('Knowledge Hub');
        $this->actingAs($editor)->get(route('admin.knowledge.create'))->assertOk()->assertSee('New Knowledge resource');
        $store = $this->actingAs($editor)->post(route('admin.knowledge.store'), [
            'title' => 'Evidence-led market guide', 'slug' => 'evidence-led-market-guide',
            'summary' => 'A test resource summary.', 'body' => 'A concise resource body.',
            'resource_type' => 'practical_guide', 'license' => 'cc-by', 'visibility' => 'public',
            'source' => 'Test research office', 'tags' => 'market, research',
            'vgi_dimension_keys' => ['market_understanding'], 'audience' => ['founders'],
        ]);
        $resource = KnowledgeResource::query()->where('slug', 'evidence-led-market-guide')->firstOrFail();
        $store->assertRedirect(route('admin.knowledge.edit', $resource->uuid));

        $this->actingAs($editor)->get(route('admin.knowledge.edit', $resource->uuid))->assertOk()
            ->assertSee('Submit for review')->assertSee('Source / provenance');
        $this->actingAs($editor)->post(route('admin.knowledge.submit', $resource->uuid))->assertRedirect();
        $this->assertSame('in_review', $resource->fresh()->status);
        $this->actingAs($editor)->get(route('admin.knowledge.edit', $resource->uuid))->assertOk()
            ->assertSee('another authorized publisher must publish');
        $this->actingAs($editor)->post(route('admin.knowledge.publish', $resource->uuid), ['review_note' => 'Self-review attempted.'])
            ->assertForbidden();

        $this->actingAs($reviewer)->post(route('admin.knowledge.publish', $resource->uuid), ['review_note' => 'Source and licence checked.'])
            ->assertRedirect();
        $this->assertSame(1, $resource->fresh()->current_version);
        $this->assertDatabaseHas('knowledge_resource_versions', ['knowledge_resource_id' => $resource->id, 'version' => 1]);
        $this->actingAs($reviewer)->get(route('admin.knowledge.versions', $resource->uuid))->assertOk()
            ->assertSee('Version 1')->assertSee('Evidence-led market guide')->assertSee('Source and licence checked.');
        $this->actingAs($reviewer)->get(route('admin.knowledge.index', ['status' => 'published']))->assertOk()
            ->assertSee('Evidence-led market guide');
    }

    public function test_category_management_screen_renders_and_prevents_deleting_in_use_categories(): void
    {
        $editor = User::factory()->withRole('content_editor')->create();
        $category = KnowledgeCategory::query()->create(['name' => 'Market research', 'slug' => 'market-research']);
        KnowledgeResource::query()->create([
            'slug' => 'categorized-guide', 'title' => 'Categorized guide', 'resource_type' => 'practical_guide',
            'license' => 'cc-by', 'visibility' => 'public', 'category_id' => $category->id,
        ]);

        $this->actingAs($editor)->get(route('admin.knowledge.categories.index'))->assertOk()
            ->assertSee('Knowledge categories')->assertSee('Market research');
        $this->actingAs($editor)->delete(route('admin.knowledge.categories.destroy', $category))
            ->assertRedirect()->assertSessionHasErrors('category');
        $this->assertDatabaseHas('knowledge_categories', ['id' => $category->id]);
    }

    public function test_non_editors_cannot_open_admin_knowledge_pages(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.knowledge.index'))->assertForbidden();
        $this->assertSame(0, KnowledgeResourceVersion::query()->count());
    }
}
