<?php

namespace Tests\Feature;

use App\Models\Page;
use Tests\TestCase;

class PageRevisionsTest extends TestCase
{
    public function test_each_save_creates_a_revision_and_restore_returns_to_draft(): void
    {
        $admin = $this->userWithRole('ecosystem_manager');
        $input = ['title' => 'First title', 'slug' => 'story', 'template' => 'default', 'status' => 'published',
            'blocks' => [['type' => 'text', 'heading' => 'Version one', 'body' => 'One']]];

        $this->actingAs($admin)->post('/admin/pages', $input);
        $page = Page::query()->where('slug', 'story')->firstOrFail();

        $input['title'] = 'Second title';
        $input['blocks'][0]['heading'] = 'Version two';
        $this->actingAs($admin)->put("/admin/pages/{$page->uuid}", $input);
        $this->assertSame(2, $page->revisions()->count());

        $first = $page->revisions()->reorder('id')->first();
        $this->actingAs($admin)->post("/admin/pages/{$page->uuid}/revisions/{$first->id}/restore")->assertRedirect();

        $page->refresh();
        $this->assertSame('First title', $page->title);
        $this->assertSame('Version one', $page->blocks[0]['heading']);
        $this->assertSame('draft', $page->status);
        $this->assertSame(3, $page->revisions()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'page.restored']);
    }

    public function test_revision_of_another_page_cannot_be_restored(): void
    {
        $admin = $this->userWithRole('super_admin');
        $a = Page::query()->create(['title' => 'A', 'slug' => 'a', 'status' => 'draft']);
        $b = Page::query()->create(['title' => 'B', 'slug' => 'b', 'status' => 'draft']);
        $revision = $b->snapshot(null);

        $this->actingAs($admin)->post("/admin/pages/{$a->uuid}/revisions/{$revision->id}/restore")->assertNotFound();
    }

    public function test_block_links_with_unsafe_schemes_are_dropped(): void
    {
        $admin = $this->userWithRole('super_admin');
        $this->actingAs($admin)->post('/admin/pages', [
            'title' => 'Links', 'slug' => 'links', 'template' => 'default', 'status' => 'draft',
            'blocks' => [['type' => 'cta', 'heading' => 'Go', 'link_label' => 'Click', 'link_url' => 'javascript:alert(1)']],
        ]);

        $this->assertSame('', Page::query()->where('slug', 'links')->first()->blocks[0]['link_url']);
    }

    public function test_home_page_cannot_be_deleted(): void
    {
        $home = Page::query()->create(['title' => 'Home', 'slug' => 'home', 'status' => 'draft']);

        $this->actingAs($this->userWithRole('super_admin'))->delete("/admin/pages/{$home->uuid}")->assertStatus(422);
        $this->assertNotSoftDeleted($home);
    }
}
