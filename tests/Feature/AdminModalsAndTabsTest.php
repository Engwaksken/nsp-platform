<?php

namespace Tests\Feature;

use App\Models\Communication\Announcement;
use App\Models\Organizations\Organization;
use App\Support\Features;
use Tests\TestCase;

/** Admin add/edit forms open in popups, and long pages or forms are split into tabs. */
class AdminModalsAndTabsTest extends TestCase
{
    public function test_communications_page_has_tabs_and_a_new_announcement_popup(): void
    {
        $admin = $this->userWithRole('platform_admin');

        $this->actingAs($admin)->get(route('admin.communications.index'))->assertOk()
            ->assertSee('data-tab="Announcements"', false)
            ->assertSee('data-tab="Email delivery log"', false)
            ->assertSee('id="new-announcement"', false)
            ->assertSee('data-inline-modal', false)
            ->assertSee('data-modal-trigger="New announcement"', false);
    }

    public function test_draft_announcements_can_be_edited_but_sent_ones_cannot(): void
    {
        $admin = $this->userWithRole('platform_admin');
        $this->actingAs($admin)->post(route('admin.communications.store'), [
            'title' => 'Demo day', 'body' => 'Join us on Friday.', 'audience' => array_key_first(Announcement::AUDIENCES),
        ])->assertRedirect();
        $draft = Announcement::query()->sole();

        $this->actingAs($admin)->get(route('admin.communications.index'))->assertOk()
            ->assertSee(route('admin.communications.edit', $draft), false);
        $this->actingAs($admin)->get(route('admin.communications.edit', $draft))->assertOk()->assertSee('Edit draft announcement')->assertSee('Demo day');

        $this->actingAs($admin)->put(route('admin.communications.update', $draft), [
            'title' => 'Demo day moved', 'body' => 'Now on Saturday.', 'audience' => $draft->audience,
        ])->assertRedirect(route('admin.communications.index'));
        $this->assertSame('Demo day moved', $draft->fresh()->title);
        $this->assertDatabaseHas('audit_logs', ['action' => 'communications.announcement_updated']);

        $this->actingAs($admin)->put(route('admin.communications.update', $draft), ['title' => '', 'body' => '', 'audience' => 'nobody'])
            ->assertSessionHasErrors(['title', 'body', 'audience']);

        $draft->forceFill(['status' => 'sent'])->save();
        $this->actingAs($admin)->get(route('admin.communications.edit', $draft))->assertNotFound();
        $this->actingAs($admin)->put(route('admin.communications.update', $draft), ['title' => 'x', 'body' => 'y', 'audience' => $draft->audience])->assertNotFound();
    }

    public function test_organization_forms_open_in_popups(): void
    {
        Features::set('organizations', true, null);
        $admin = $this->userWithRole('platform_admin');
        $organization = Organization::query()->create(['name' => 'Kampala Hub', 'slug' => 'kampala-hub', 'type' => 'eso', 'status' => 'pending', 'created_by' => $admin->id]);

        $this->actingAs($admin)->get(route('admin.organizations.index'))->assertOk()
            ->assertSee('data-modal-trigger="New organization"', false);
        $this->actingAs($admin)->get(route('admin.organizations.show', $organization))->assertOk()
            ->assertSee('data-modal-trigger="Edit details"', false)
            ->assertSee('data-modal-trigger="Invite someone"', false)
            ->assertSee('class="detail-grid"', false);
    }

    public function test_long_opportunity_form_is_split_into_tabs(): void
    {
        Features::set('opportunities', true, null);
        $admin = $this->userWithRole('platform_admin');

        $this->actingAs($admin)->get(route('admin.opportunities.create'))->assertOk()
            ->assertSee('data-tab="Details"', false)
            ->assertSee('data-tab="Value, place and dates"', false)
            ->assertSee('data-tab="Who can apply"', false)
            ->assertSee('data-tab="Registration on NSP"', false);
    }
}
