<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\LoginAudit;
use App\Models\User;
use Tests\TestCase;

class AuditLogPageTest extends TestCase
{
    public function test_rows_are_summaries_and_the_full_entry_opens_from_view(): void
    {
        $admin = $this->userWithRole('platform_admin');
        $actor = User::factory()->create(['name' => 'Amina Okello', 'email' => 'amina@example.org']);
        $log = AuditLog::query()->create([
            'actor_id' => $actor->id, 'action' => 'talent.status_changed', 'subject_type' => 'App\\Models\\Talent\\JobApplication', 'subject_id' => '42',
            'changes' => ['from' => 'submitted', 'to' => 'interview', 'notes' => ['a' => 1], 'extra' => 'x'], 'user_agent' => 'Mozilla/5.0 Test',
        ]);
        AuditLog::query()->create(['action' => 'page.updated', 'subject_type' => 'pages', 'subject_id' => '7']);

        $this->actingAs($admin)->get('/admin/audit')->assertOk()
            ->assertSee('Changes today')->assertSee('Amina Okello')->assertSee('Status changed')->assertSee('Job Application')
            ->assertSee('+1 more')->assertSee(route('admin.audit.show', $log), false)->assertSee('data-modal="lg"', false)
            ->assertDontSee('Mozilla/5.0 Test');

        $this->actingAs($admin)->get(route('admin.audit.show', $log))->assertOk()
            ->assertSee('talent.status_changed')->assertSee('amina@example.org')->assertSee('Mozilla/5.0 Test')
            ->assertSee('interview')->assertSee('Raw JSON');

        $this->actingAs($admin)->get('/admin/audit?action=talent.')->assertOk()->assertSee('Amina Okello')->assertDontSee('Updated');
        $this->actingAs($admin)->get('/admin/audit?who=amina')->assertOk()->assertSee('1 entry matches your filters');
        $this->actingAs($admin)->get('/admin/audit?who=nobody')->assertOk()->assertSee('No entries match these filters.');
        $this->actingAs($admin)->get('/admin/audit?from=not-a-date')->assertSessionHasErrors('from');
    }

    public function test_sign_ins_tab_filters_and_opens_details(): void
    {
        $admin = $this->userWithRole('platform_admin');
        $ok = LoginAudit::query()->create(['user_id' => $admin->id, 'method' => 'password', 'success' => true]);
        LoginAudit::query()->create(['user_id' => null, 'method' => 'password', 'success' => false, 'reason' => 'bad_credentials', 'ip_hash' => str_repeat('a', 64)]);

        $this->actingAs($admin)->get('/admin/audit?tab=logins')->assertOk()->assertSee('Failed today')->assertSee('Bad credentials');
        $this->actingAs($admin)->get('/admin/audit?tab=logins&result=success')->assertOk()->assertDontSee('Bad credentials');
        $this->actingAs($admin)->get(route('admin.audit.login', $ok))->assertOk()->assertSee('Signed in successfully')->assertSee($admin->email);
    }

    public function test_details_need_the_audit_permission(): void
    {
        $log = AuditLog::query()->create(['action' => 'page.updated']);
        $login = LoginAudit::query()->create(['method' => 'password', 'success' => true]);
        $editor = $this->userWithRole('content_editor');
        $this->actingAs($editor)->get(route('admin.audit.show', $log))->assertForbidden();
        $this->actingAs($editor)->get(route('admin.audit.login', $login))->assertForbidden();
    }
}
