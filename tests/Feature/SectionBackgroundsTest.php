<?php

namespace Tests\Feature;

use App\Models\Faq;
use App\Models\Media;
use App\Models\Page;
use App\Support\PageBlocks;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Page-builder sections can have a colour, image or video background. */
class SectionBackgroundsTest extends TestCase
{
    private function media(string $mime, string $name): Media
    {
        return Media::query()->create(['disk' => 'public', 'path' => 'media/'.$name, 'original_name' => $name, 'mime_type' => $mime, 'size' => 1000]);
    }

    private function save(array $blocks)
    {
        return $this->actingAs($this->userWithRole('super_admin'))->post('/admin/pages', [
            'title' => 'Story', 'slug' => 'story', 'template' => 'default', 'status' => 'published', 'blocks' => $blocks,
        ]);
    }

    public function test_colour_image_and_video_backgrounds_are_saved_and_rendered(): void
    {
        $image = $this->media('image/jpeg', 'field.jpg');
        $video = $this->media('video/mp4', 'farm.mp4');

        $this->save([
            ['type' => 'text', 'heading' => 'Dark colour', 'body' => 'x', 'background' => 'color', 'bg_color' => '#0F3D5E', 'bg_media_id' => (string) $image->id],
            ['type' => 'text', 'heading' => 'Light colour', 'body' => 'x', 'background' => 'color', 'bg_color' => '#fef3c7'],
            ['type' => 'hero', 'heading' => 'Photo hero', 'background' => 'image', 'bg_media_id' => (string) $image->id, 'bg_color' => '#000000'],
            ['type' => 'cta', 'heading' => 'Moving', 'background' => 'video', 'bg_media_id' => (string) $video->id],
            ['type' => 'stats', 'heading' => 'Plain', 'items' => '10 | Startups'],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $blocks = Page::query()->where('slug', 'story')->firstOrFail()->blocks;
        $this->assertSame(['color', '#0f3d5e', null], [$blocks[0]['background'], $blocks[0]['bg_color'], $blocks[0]['bg_media_id']]);
        $this->assertSame(['image', '', $image->id], [$blocks[2]['background'], $blocks[2]['bg_color'], $blocks[2]['bg_media_id']]);

        $html = $this->get('/story')->assertOk()->getContent();
        $this->assertStringContainsString('has-bg has-bg-color on-dark" style="--section-bg: #0f3d5e"', $html);
        $this->assertStringContainsString('has-bg has-bg-color on-light" style="--section-bg: #fef3c7"', $html);
        $this->assertStringContainsString('hero has-bg has-bg-image on-dark', $html);
        $this->assertStringContainsString('--section-bg-image: url(&quot;'.$image->url().'&quot;)', $html);
        $this->assertStringContainsString('<source src="'.$video->url().'" type="video/mp4">', $html);
        $this->assertStringContainsString('autoplay muted loop playsinline', $html);
        $this->assertStringContainsString('section section-alt', $html); // the plain stats section keeps its default look
    }

    public function test_background_needs_the_right_kind_of_file_and_a_colour(): void
    {
        $image = $this->media('image/png', 'logo.png');
        $pdf = $this->media('application/pdf', 'report.pdf');

        $this->save([['type' => 'text', 'heading' => 'A', 'background' => 'video', 'bg_media_id' => (string) $image->id]])
            ->assertSessionHasErrors(['blocks.0.bg_media_id' => 'Section 1: choose a video from the media library for the background.']);
        $this->save([['type' => 'text', 'heading' => 'A', 'background' => 'image', 'bg_media_id' => (string) $pdf->id]])->assertSessionHasErrors('blocks.0.bg_media_id');
        $this->save([['type' => 'text', 'heading' => 'A', 'background' => 'image']])->assertSessionHasErrors('blocks.0.bg_media_id');
        $this->save([['type' => 'text', 'heading' => 'A', 'background' => 'color', 'bg_color' => 'red; background:url(x)']])->assertSessionHasErrors('blocks.0.bg_color');
        $this->save([['type' => 'text', 'heading' => 'A', 'background' => 'sparkles']])->assertSessionHasErrors('blocks.0.background');
        $this->assertSame(0, Page::query()->count());
    }

    public function test_a_deleted_background_file_falls_back_to_the_default_look(): void
    {
        $video = $this->media('video/webm', 'loop.webm');
        $this->save([['type' => 'text', 'heading' => 'Gone', 'body' => 'x', 'background' => 'video', 'bg_media_id' => (string) $video->id]])->assertSessionHasNoErrors();
        Media::query()->whereKey($video->id)->delete();

        $html = $this->get('/story')->assertOk()->assertSee('Gone')->getContent();
        $this->assertStringNotContainsString('has-bg', $html);
        $this->assertStringNotContainsString('<video', $html);
    }

    public function test_editor_shows_background_controls_with_images_and_videos(): void
    {
        $this->media('image/jpeg', 'field.jpg');
        $this->media('video/mp4', 'farm.mp4');
        $this->media('image/x-icon', 'favicon.ico');

        $html = $this->actingAs($this->userWithRole('super_admin'))->get('/admin/pages/create')->assertOk()
            ->assertSee('Background')->assertSee('type="color"', false)->assertSee('<optgroup label="Videos">', false)
            ->assertSee('farm.mp4')->assertSee('field.jpg')->getContent();
        // Icons are offered as the sharing image only, never as a section background.
        $this->assertSame(1, substr_count($html, 'favicon.ico'));
    }

    public function test_videos_can_be_uploaded_up_to_20_mb_but_images_stay_at_5_mb(): void
    {
        Storage::fake('public');
        $editor = $this->userWithRole('content_editor');

        $this->actingAs($editor)->post('/admin/media', ['file' => UploadedFile::fake()->create('loop.mp4', 8000, 'video/mp4')])->assertSessionHasNoErrors();
        $this->assertTrue(Media::query()->firstOrFail()->isVideo());
        $this->actingAs($editor)->post('/admin/media', ['file' => UploadedFile::fake()->create('huge.mp4', 25000, 'video/mp4')])->assertSessionHasErrors('file');
        $this->actingAs($editor)->post('/admin/media', ['file' => UploadedFile::fake()->image('big.jpg')->size(8000)])->assertSessionHasErrors('file');
        $this->actingAs($editor)->get('/admin/media?type=videos')->assertOk()->assertSee('loop.mp4');
    }

    public function test_faq_band_shows_three_questions(): void
    {
        foreach (range(1, 5) as $n) {
            Faq::query()->create(['question' => "Question number {$n}?", 'answer' => 'Yes.', 'is_published' => true, 'sort_order' => $n]);
        }
        $this->get('/')->assertOk()->assertSee('Question number 3?')->assertDontSee('Question number 4?')->assertSee('View all FAQs');
        $this->assertSame(3, PageBlocks::FAQ_LIMIT);
    }
}
