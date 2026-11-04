<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Page;
use App\Models\SeoRedirect;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RedirectsAndMediaTest extends TestCase
{
    public function test_redirect_is_followed_for_missing_paths_and_counts_hits(): void
    {
        SeoRedirect::query()->create(['from_path' => '/old/programs', 'to_url' => '/programs', 'status_code' => 301]);

        $this->get('/old/programs/')->assertRedirect('/programs')->assertStatus(301);
        $this->assertSame(1, SeoRedirect::query()->first()->hits);
    }

    public function test_existing_page_wins_over_a_redirect(): void
    {
        Page::query()->create(['title' => 'About', 'slug' => 'about', 'status' => 'published', 'published_at' => now()]);
        SeoRedirect::query()->create(['from_path' => '/about', 'to_url' => '/elsewhere', 'status_code' => 301]);

        $this->get('/about')->assertOk();
    }

    public function test_redirect_targets_must_be_safe(): void
    {
        $admin = $this->userWithRole('platform_admin');

        $this->actingAs($admin)->post('/admin/redirects', ['from_path' => '/a', 'to_url' => 'javascript:alert(1)', 'status_code' => 301])
            ->assertSessionHasErrors('to_url');
        $this->actingAs($admin)->post('/admin/redirects', ['from_path' => '/a', 'to_url' => '/a', 'status_code' => 301])
            ->assertSessionHasErrors('from_path');
        $this->actingAs($admin)->post('/admin/redirects', ['from_path' => '/Old-Page', 'to_url' => 'https://example.org/new', 'status_code' => 302])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('seo_redirects', ['from_path' => '/old-page']);
    }

    public function test_images_upload_with_random_names(): void
    {
        Storage::fake('public');

        $this->actingAs($this->userWithRole('content_editor'))
            ->post('/admin/media', ['file' => UploadedFile::fake()->image('team photo.png', 400, 300), 'alt_text' => 'Team'])
            ->assertSessionHasNoErrors();

        $media = Media::query()->firstOrFail();
        $this->assertSame('image/png', $media->mime_type);
        $this->assertStringNotContainsString('team photo', $media->path);
        Storage::disk('public')->assertExists($media->path);
    }

    public function test_scripts_disguised_as_images_are_rejected(): void
    {
        Storage::fake('public');
        // A real file (not UploadedFile::fake) so the MIME type is sniffed from its contents, as in production.
        $path = tempnam(sys_get_temp_dir(), 'nsp');
        file_put_contents($path, "<?php echo 'pwned'; ?>");
        $disguised = new UploadedFile($path, 'shell.png', null, null, true);

        $this->actingAs($this->userWithRole('content_editor'))->post('/admin/media', ['file' => $disguised])->assertSessionHasErrors('file');
        @unlink($path);
        $this->actingAs($this->userWithRole('content_editor'))
            ->post('/admin/media', ['file' => UploadedFile::fake()->createWithContent('x.php', '<?php')])->assertSessionHasErrors('file');
        $this->assertSame(0, Media::query()->count());
    }

    public function test_deleting_media_removes_the_file(): void
    {
        Storage::fake('public');
        $admin = $this->userWithRole('content_editor');
        $this->actingAs($admin)->post('/admin/media', ['file' => UploadedFile::fake()->image('a.jpg')]);
        $media = Media::query()->firstOrFail();

        $this->actingAs($admin)->delete("/admin/media/{$media->id}")->assertRedirect();
        Storage::disk('public')->assertMissing($media->path);
    }
}
