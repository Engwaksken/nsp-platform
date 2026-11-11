<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\MapEmbed;
use Illuminate\Support\Once;
use Tests\TestCase;

/** Contact page layout and location map, and the tabbed account, dashboard and admin settings pages. */
class ContactAndTabsTest extends TestCase
{
    private const SETTINGS = ['site_name' => 'Hive NSP', 'primary_color' => '#3a3a3c', 'secondary_color' => '#f7941d', 'accent_color' => '#f1592a', 'font_family' => 'Poppins'];

    public function test_map_embed_accepts_only_google_and_openstreetmap_embeds(): void
    {
        $google = 'https://www.google.com/maps/embed?pb=!1m18!1m12';
        $this->assertSame($google, MapEmbed::normalize($google));
        $this->assertSame($google, MapEmbed::normalize('<iframe src="'.$google.'" width="600" height="450" style="border:0;" allowfullscreen=""></iframe>'));
        $osm = 'https://www.openstreetmap.org/export/embed.html?bbox=32.5,0.3,32.6,0.4&layer=mapnik';
        $this->assertSame($osm, MapEmbed::normalize($osm));

        foreach (['', 'http://www.google.com/maps/embed?pb=1', 'https://evil.example/maps/embed', 'https://www.google.com/search?q=x',
            'javascript:alert(1)', '<iframe src="https://evil.example/x"></iframe>', 'https://maps.google.com/maps?q=Kampala'] as $bad) {
            $this->assertNull(MapEmbed::normalize($bad), $bad);
        }
    }

    public function test_admin_sets_a_map_and_the_contact_page_shows_form_details_and_map(): void
    {
        $admin = $this->userWithRole('platform_admin');
        $map = 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3';

        $this->actingAs($admin)->get('/admin/settings')->assertOk()
            ->assertSee('data-tab="Site identity"', false)
            ->assertSee('data-tab="Colours and font"', false)
            ->assertSee('data-tab="Contact details"', false)
            ->assertSee('data-tab="Contact form routing"', false)
            ->assertSee('data-tab="Social links"', false);

        $this->actingAs($admin)->put('/admin/settings', self::SETTINGS + ['contact_map' => 'https://evil.example/frame'])
            ->assertSessionHasErrors('contact_map');

        $this->actingAs($admin)->put('/admin/settings', self::SETTINGS + [
            'contact_email' => 'hello@hive.test', 'contact_phone' => '+256 700 000000', 'contact_address' => 'Plot 1, Kampala',
            'contact_map' => '<iframe src="'.$map.'" width="600" height="450"></iframe>',
        ])->assertSessionHasNoErrors();

        auth()->logout();
        Once::flush(); // branding is memoised per process; a real request starts fresh
        $page = $this->get('/contact')->assertOk()
            ->assertSee('class="contact-layout"', false)
            ->assertSee('Send message')
            ->assertSee('mailto:hello@hive.test', false)
            ->assertSee('tel:+256700000000', false)
            ->assertSee('Plot 1, Kampala')
            ->assertSee('<iframe src="'.e($map).'"', false);
        $this->assertStringContainsString('frame-src https://www.google.com', $page->headers->get('Content-Security-Policy'));

        // Other pages may not frame third-party sites.
        $this->assertStringContainsString("frame-src 'self'", $this->get('/')->headers->get('Content-Security-Policy'));
    }

    public function test_contact_page_without_details_or_map_still_works(): void
    {
        $this->get('/contact')->assertOk()->assertSee('Use the form and we will reply by email.')->assertDontSee('<iframe', false);
    }

    public function test_account_and_dashboard_sections_are_tabs(): void
    {
        $member = User::factory()->withRole('startup_user')->create();

        $this->actingAs($member)->get('/account')->assertOk()
            ->assertSee('data-tab="Personal details"', false)
            ->assertSee('data-tab="Password"', false)
            ->assertSee('data-tab="Appearance"', false)
            ->assertSee('data-tab="More settings"', false);

        $this->actingAs($member)->get('/workspace')->assertOk()
            ->assertSee('data-tab="Quick actions"', false)
            ->assertSee('data-tab="Services"', false);
    }
}
