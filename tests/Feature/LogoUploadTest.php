<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use App\Support\LogoImage;
use App\Support\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Uploaded site logos are trimmed and resized to one size, and shown on the public account forms. */
class LogoUploadTest extends TestCase
{
    /** A PNG of the given size: a dark box in the middle on a white (or transparent) background. */
    private function logoFile(int $width, int $height, int $boxW, int $boxH, bool $transparent = false): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagesavealpha($image, true);
        imagealphablending($image, false);
        imagefill($image, 0, 0, $transparent ? imagecolorallocatealpha($image, 0, 0, 0, 127) : imagecolorallocate($image, 255, 255, 255));
        $x = intdiv($width - $boxW, 2);
        $y = intdiv($height - $boxH, 2);
        imagefilledrectangle($image, $x, $y, $x + $boxW - 1, $y + $boxH - 1, imagecolorallocate($image, 20, 40, 90));
        $path = tempnam(sys_get_temp_dir(), 'logo').'.png';
        imagepng($image, $path);
        unset($image);

        return $path;
    }

    private function dimensions(string $data): array
    {
        $info = getimagesizefromstring($data);

        return [$info[0], $info[1]];
    }

    public function test_white_margins_are_trimmed_and_large_logos_scaled_to_fit(): void
    {
        // 900×900 canvas with an 800×200 logo in the middle: lots of empty space around it.
        $result = LogoImage::prepare($this->logoFile(900, 900, 800, 200));

        $this->assertNotNull($result);
        [$w, $h] = $this->dimensions($result['data']);
        $this->assertSame([$w, $h], [$result['width'], $result['height']]);
        $this->assertLessThanOrEqual(LogoImage::MAX_WIDTH, $w);
        $this->assertLessThanOrEqual(LogoImage::MAX_HEIGHT, $h);
        // The shape of the artwork (4:1) is kept, not the square canvas.
        $this->assertEqualsWithDelta(4.0, $w / $h, 0.35);
    }

    public function test_transparent_margins_are_trimmed_and_transparency_kept(): void
    {
        $result = LogoImage::prepare($this->logoFile(800, 800, 400, 100, transparent: true));

        [$w, $h] = $this->dimensions($result['data']);
        $this->assertEqualsWithDelta(4.0, $w / $h, 0.35);
        $image = imagecreatefromstring($result['data']);
        $corner = imagecolorsforindex($image, imagecolorat($image, 0, 0));
        $this->assertSame(127, $corner['alpha'], 'The padding around the logo stays transparent.');
    }

    public function test_small_logos_are_never_enlarged(): void
    {
        $result = LogoImage::prepare($this->logoFile(120, 40, 120, 40));

        $this->assertSame([120, 40], $this->dimensions($result['data']));
    }

    public function test_oversized_logos_are_refused_with_a_clear_message(): void
    {
        Storage::fake('public');
        $admin = User::factory()->withRole('platform_admin')->create();

        $this->actingAs($admin)->withSession(['mfa_verified' => true])->put('/admin/settings', [
            'site_name' => 'Hive Growth', 'primary_color' => '#123456', 'secondary_color' => '#0f766e',
            'accent_color' => '#b45309', 'font_family' => 'Outfit',
            'logo_upload' => UploadedFile::fake()->image('huge.png', LogoImage::MAX_SOURCE_SIDE + 1, 100),
        ])->assertSessionHasErrors(['logo_upload' => 'The logo can be at most 3000 × 3000 pixels. Export a smaller version and try again.']);

        $this->assertSame(0, Media::query()->count());
    }

    public function test_unreadable_files_are_left_alone(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'logo');
        file_put_contents($path, 'not an image');

        $this->assertNull(LogoImage::prepare($path));
    }

    public function test_uploaded_logo_is_stored_resized_and_shown_on_header_and_account_forms(): void
    {
        Storage::fake('public');
        $admin = User::factory()->withRole('platform_admin')->create();
        $file = new UploadedFile($this->logoFile(900, 900, 800, 200), 'brand.png', 'image/png', null, true);

        $this->actingAs($admin)->withSession(['mfa_verified' => true])->put('/admin/settings', [
            'site_name' => 'Hive Growth', 'primary_color' => '#123456', 'secondary_color' => '#0f766e',
            'accent_color' => '#b45309', 'font_family' => 'Outfit', 'logo_upload' => $file,
        ])->assertSessionHas('status', 'Settings saved.');

        $media = Media::query()->findOrFail((int) Settings::get('logo_media_id'));
        $this->assertSame('image/png', $media->mime_type);
        $this->assertStringEndsWith('.png', $media->path);
        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($media->path));
        $this->assertLessThanOrEqual(LogoImage::MAX_HEIGHT, $h);
        $this->assertEqualsWithDelta(4.0, $w / $h, 0.35);

        // The settings form previews it; the header and every public account form show it.
        $this->get('/admin/settings')->assertSeeInOrder(['id="logo-preview-img"', 'src="'.$media->url().'"', 'data-image-preview="logo-preview-img"'], false);
        auth()->logout();
        $this->get('/')->assertSee($media->url(), false);
        foreach (['/login', '/register', '/forgot-password'] as $page) {
            $this->get($page)->assertOk()->assertSee('class="auth-brand"', false)->assertSee($media->url(), false);
        }
    }

    public function test_account_forms_show_the_site_name_when_there_is_no_logo(): void
    {
        $this->get('/login')->assertOk()->assertSee('class="auth-brand"', false)->assertSee(Settings::get('site_name'));
    }
}
