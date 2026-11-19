<?php

namespace Tests\Feature;

use App\Support\AssetVersion;
use Tests\TestCase;

/** Our CSS and JS are linked with their change time, so browsers and the service worker never serve a stale copy. */
class AssetVersionTest extends TestCase
{
    public function test_pages_link_css_and_js_with_their_change_time(): void
    {
        $page = $this->get('/login')->assertOk();

        foreach (['css/nsp.css', 'js/nsp.js', 'css/widgets.css', 'js/theme.js'] as $file) {
            $page->assertSee(asset($file).'?v='.filemtime(public_path($file)), false);
        }
        $this->assertSame(asset('css/nsp.css').'?v='.filemtime(public_path('css/nsp.css')), AssetVersion::url('css/nsp.css'));
    }

    public function test_form_logo_has_a_height_before_the_stylesheet_loads(): void
    {
        $view = file_get_contents(resource_path('views/partials/auth-brand.blade.php'));

        $this->assertStringContainsString('height="52" decoding="async">', $view);
    }
}
