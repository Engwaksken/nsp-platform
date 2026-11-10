<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use App\Http\Controllers\Admin\FeatureFlagController;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Auth\Notifications\VerifyEmail;
use Tests\TestCase;

class AdminPermissionsTest extends TestCase
{
    private function pageInput(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Programs', 'slug' => 'programs', 'template' => 'default', 'status' => 'draft',
            'blocks' => [['type' => 'text', 'heading' => 'Intro', 'body' => 'Text']],
        ], $overrides);
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_users_without_an_admin_role_are_forbidden(): void
    {
        foreach (['startup_user', 'investor', 'expert'] as $role) {
            $this->actingAs($this->userWithRole($role))->get('/admin')->assertForbidden();
        }
    }

    public function test_inactive_admin_is_signed_out(): void
    {
        $admin = User::factory()->inactive()->withRole('platform_admin')->create();

        $this->actingAs($admin)->get('/admin')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_content_editor_can_save_drafts_but_not_publish(): void
    {
        $editor = $this->userWithRole('content_editor');

        $this->actingAs($editor)->post('/admin/pages', $this->pageInput())->assertRedirect();
        $this->assertDatabaseHas('pages', ['slug' => 'programs', 'status' => 'draft']);

        $this->actingAs($editor)->post('/admin/pages', $this->pageInput(['slug' => 'news-2', 'status' => 'published']))->assertForbidden();
        $this->assertDatabaseMissing('pages', ['slug' => 'news-2']);
    }

    public function test_content_editor_cannot_change_a_live_page(): void
    {
        $page = Page::query()->create(['title' => 'Live', 'slug' => 'live', 'status' => 'published', 'published_at' => now()]);

        $this->actingAs($this->userWithRole('content_editor'))
            ->put("/admin/pages/{$page->uuid}", $this->pageInput(['slug' => 'live', 'status' => 'draft']))
            ->assertForbidden();
        $this->assertSame('published', $page->fresh()->status);
    }

    public function test_content_editor_cannot_reach_settings_users_or_audit(): void
    {
        $editor = $this->userWithRole('content_editor');

        foreach (['/admin/settings', '/admin/users', '/admin/audit', '/admin/features', '/admin/messages'] as $path) {
            $this->actingAs($editor)->get($path)->assertForbidden();
        }
    }

    public function test_ecosystem_manager_can_publish(): void
    {
        $this->actingAs($this->userWithRole('ecosystem_manager'))
            ->post('/admin/pages', $this->pageInput(['status' => 'published']))
            ->assertRedirect();

        $page = Page::query()->where('slug', 'programs')->firstOrFail();
        $this->assertTrue($page->isLive());
        $this->get('/programs')->assertOk();
    }

    public function test_reserved_and_invalid_slugs_are_rejected(): void
    {
        $admin = $this->userWithRole('platform_admin');

        $this->actingAs($admin)->post('/admin/pages', $this->pageInput(['slug' => 'admin']))->assertSessionHasErrors('slug');
        $this->actingAs($admin)->post('/admin/pages', $this->pageInput(['slug' => 'Bad Slug']))->assertSessionHasErrors('slug');
    }

    public function test_preview_shows_drafts_only_to_editors(): void
    {
        $page = Page::query()->create(['title' => 'Secret plan', 'slug' => 'secret', 'status' => 'draft']);

        $this->get("/admin/pages/{$page->uuid}/preview")->assertRedirect('/login');
        $this->actingAs($this->userWithRole('startup_user'))->get("/admin/pages/{$page->uuid}/preview")->assertForbidden();
        $this->actingAs($this->userWithRole('content_editor'))->get("/admin/pages/{$page->uuid}/preview")->assertOk()->assertSee('Secret plan');
    }

    public function test_only_super_admin_can_grant_privileged_roles(): void
    {
        $platformAdmin = $this->userWithRole('platform_admin');
        $target = $this->userWithRole('content_editor');
        $input = ['name' => $target->name, 'email' => $target->email, 'roles' => ['super_admin'], 'is_active' => 1];

        $this->actingAs($platformAdmin)->put("/admin/users/{$target->id}", $input)->assertForbidden();
        $this->assertFalse($target->fresh()->hasRole('super_admin'));

        $this->actingAs($this->userWithRole('super_admin'))->put("/admin/users/{$target->id}", $input)->assertRedirect();
        $this->assertTrue($target->fresh()->hasRole('super_admin'));
    }

    public function test_platform_admin_cannot_edit_a_super_admin(): void
    {
        $super = $this->userWithRole('super_admin');

        $this->actingAs($this->userWithRole('platform_admin'))
            ->put("/admin/users/{$super->id}", ['name' => 'x', 'email' => $super->email, 'roles' => [], 'is_active' => 0])
            ->assertForbidden();
        $this->assertTrue($super->fresh()->is_active);
    }

    public function test_admin_cannot_lock_themselves_out(): void
    {
        $admin = $this->userWithRole('super_admin');

        $this->actingAs($admin)
            ->put("/admin/users/{$admin->id}", ['name' => $admin->name, 'email' => $admin->email, 'roles' => ['super_admin'], 'is_active' => 0])
            ->assertSessionHasErrors('roles');
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_settings_change_branding_and_are_audited(): void
    {
        $this->actingAs($this->userWithRole('platform_admin'))->put('/admin/settings', [
            'site_name' => 'Hive Growth', 'primary_color' => '#123456', 'secondary_color' => '#0f766e',
            'accent_color' => '#b45309', 'font_family' => 'Outfit',
        ])->assertRedirect();

        $this->get('/')->assertSee('Hive Growth')->assertSee('--brand-primary: #123456', false)->assertSee('fonts/outfit/outfit-latin.woff2', false)->assertDontSee('fonts.googleapis.com', false);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.updated']);
    }

    public function test_settings_reject_invalid_colours(): void
    {
        $this->actingAs($this->userWithRole('platform_admin'))->put('/admin/settings', [
            'site_name' => 'x', 'primary_color' => 'red;}</style><script>', 'secondary_color' => '#000000',
            'accent_color' => '#000000', 'font_family' => 'Poppins',
        ])->assertSessionHasErrors('primary_color');
    }

    public function test_admin_can_upload_a_logo_and_use_it_globally(): void
    {
        Storage::fake('public');

        $this->actingAs($this->userWithRole('platform_admin'))->put('/admin/settings', [
            'site_name' => 'Hive Growth', 'primary_color' => '#123456', 'secondary_color' => '#0f766e',
            'accent_color' => '#b45309', 'font_family' => 'Outfit',
            'logo_upload' => UploadedFile::fake()->image('logo.png'),
        ])->assertRedirect()->assertSessionHas('status', 'Settings saved.');

        $media = \App\Models\Media::query()->firstOrFail();
        Storage::disk('public')->assertExists($media->path);
        $this->assertSame($media->id, (int) \App\Support\Settings::get('logo_media_id'));
        $this->get('/')->assertSee($media->url(), false);
    }

    public function test_admin_created_users_receive_a_working_email_confirmation_link(): void
    {
        Notification::fake();
        $admin = $this->userWithRole('platform_admin');

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'New Member', 'email' => 'new.member@example.test', 'password' => '',
            'roles' => ['startup_user'], 'is_active' => 1,
        ])->assertRedirect('/admin/users')->assertSessionHas('status', 'User created. A confirmation email was sent.');

        $newUser = User::query()->where('email', 'new.member@example.test')->firstOrFail();
        $this->assertNull($newUser->email_verified_at);
        Notification::assertSentTo($newUser, VerifyEmail::class);

        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $newUser->id,
            'hash' => sha1($newUser->getEmailForVerification()),
        ]);
        $this->get($url)->assertRedirect('/')->assertSessionHas('status', 'Your email address has been confirmed.');
        $this->assertNotNull($newUser->fresh()->email_verified_at);
    }

    public function test_ims_setup_hint_uses_configured_ims_host(): void
    {
        config(['nsp.ims_url' => 'https://ims.hivecolab.com/']);

        $missing = FeatureFlagController::missingSetup('ims_integration');

        $this->assertContains(
            'Register Hive IMS as a connected system: php artisan nsp:api-client ims-live --name="Hive IMS" --redirect-uri=https://ims.hivecolab.com/nsp-callback --webhook-url=https://ims.hivecolab.com/nsp-webhook',
            $missing,
        );
        $this->assertNotContains('Set IMS_URL in .env so founders get a link to Hive IMS.', $missing);
    }

    public function test_every_admin_screen_renders_for_a_super_admin(): void
    {
        $admin = $this->userWithRole('super_admin');
        $paths = ['/admin', '/admin/pages', '/admin/pages/create', '/admin/posts', '/admin/posts/create', '/admin/categories',
            '/admin/media', '/admin/menus', '/admin/content/faqs', '/admin/content/testimonials/create', '/admin/content/partners',
            '/admin/redirects', '/admin/messages', '/admin/settings', '/admin/users', '/admin/users/create', '/admin/features',
            '/admin/audit', '/admin/audit?tab=logins'];

        foreach ($paths as $path) {
            $this->actingAs($admin)->get($path)->assertOk();
        }
        $this->actingAs($admin)->get('/admin/content/unknown')->assertNotFound();
    }
}
