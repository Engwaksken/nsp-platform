<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Post;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    private function page(array $attributes = []): Page
    {
        return Page::query()->create(array_merge([
            'title' => 'About us', 'slug' => 'about', 'template' => 'default', 'status' => 'published',
            'published_at' => now()->subMinute(),
            'blocks' => [['type' => 'text', 'heading' => 'Who we are', 'body' => 'We support **startups**.', 'items' => '', 'link_label' => '', 'link_url' => '']],
        ], $attributes));
    }

    public function test_home_page_renders_without_any_content(): void
    {
        $this->get('/')->assertOk()->assertSee('Hive NSP');
    }

    public function test_header_hides_links_to_draft_pages(): void
    {
        $draft = $this->page(['status' => 'draft', 'published_at' => null]);
        $live = $this->page(['title' => 'Team', 'slug' => 'team']);
        $menu = \App\Models\Menu::query()->create(['name' => 'Header', 'location' => 'header']);
        $menu->items()->create(['label' => 'About link', 'page_id' => $draft->id, 'sort_order' => 1]);
        $menu->items()->create(['label' => 'Team link', 'page_id' => $live->id, 'sort_order' => 2]);
        $menu->items()->create(['label' => 'News link', 'url' => '/news', 'sort_order' => 3]);

        $this->get('/')->assertOk()->assertDontSee('About link')->assertSee('Team link')->assertSee('News link');
    }

    public function test_header_groups_sections_into_dropdowns_and_account_menu(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('aria-controls="nav-menu-opportunities"', false)
            ->assertSee('aria-controls="nav-menu-ecosystem"', false)
            ->assertSee('Challenge Exchange')
            ->assertSee('Knowledge Hub')
            ->assertSee('Sign in')
            ->assertDontSee('nav-menu-account', false);

        $this->actingAs($this->userWithRole('startup_user'))->get('/')->assertOk()
            ->assertSee('aria-controls="nav-menu-account"', false)
            ->assertSee('My workspace')
            ->assertSee('Sign out');
    }

    public function test_footer_has_explore_information_and_contact_columns_without_duplicates(): void
    {
        $header = \App\Models\Menu::query()->create(['name' => 'Header', 'location' => 'header']);
        $header->items()->create(['label' => 'Home', 'url' => '/', 'sort_order' => 1]);
        $header->items()->create(['label' => 'FAQs', 'url' => '/faqs', 'sort_order' => 2]);
        $footer = \App\Models\Menu::query()->create(['name' => 'Footer', 'location' => 'footer']);
        $footer->items()->create(['label' => 'FAQs', 'url' => '/faqs', 'sort_order' => 1]);
        $footer->items()->create(['label' => 'Privacy', 'url' => '/privacy', 'sort_order' => 2]);

        $html = $this->get('/')->assertOk()->getContent();
        $footerHtml = substr($html, strpos($html, '<footer'));
        foreach (['id="footer-explore"', 'id="footer-info"', 'Get in touch', 'Knowledge Hub', 'Privacy', 'Back to top'] as $text) {
            $this->assertStringContainsString($text, $footerHtml);
        }
        $this->assertSame(1, substr_count($footerHtml, '>FAQs<'), 'A link in both menus is listed once.');
        $this->assertStringNotContainsString('>Home<', $footerHtml);
    }

    public function test_published_page_is_visible_and_markdown_is_rendered(): void
    {
        $this->page();

        $this->get('/about')->assertOk()->assertSee('Who we are')->assertSee('<strong>startups</strong>', false);
    }

    public function test_draft_and_archived_pages_are_hidden(): void
    {
        $this->page(['status' => 'draft']);
        $this->page(['slug' => 'old', 'status' => 'archived']);

        $this->get('/about')->assertNotFound();
        $this->get('/old')->assertNotFound();
    }

    public function test_scheduled_page_appears_only_after_its_publish_time(): void
    {
        $this->page(['published_at' => now()->addDay()]);

        $this->get('/about')->assertNotFound();
        $this->travel(2)->days();
        $this->get('/about')->assertOk();
    }

    public function test_raw_html_and_scripts_in_content_are_escaped(): void
    {
        $this->page(['blocks' => [['type' => 'text', 'heading' => '<b>x</b>', 'body' => "<script>alert(1)</script>\n\n[click](javascript:alert(1))", 'items' => '', 'link_label' => '', 'link_url' => '']]]);

        $response = $this->get('/about')->assertOk();
        $response->assertDontSee('<script>alert(1)</script>', false);
        $response->assertDontSee('javascript:alert', false);
        $response->assertDontSee('<b>x</b>', false);
    }

    public function test_news_lists_only_published_posts(): void
    {
        Post::query()->create(['title' => 'Live post', 'slug' => 'live', 'status' => 'published', 'published_at' => now()->subHour(), 'body' => 'Hello']);
        Post::query()->create(['title' => 'Secret draft', 'slug' => 'draft', 'status' => 'draft', 'body' => 'Hidden']);

        $this->get('/news')->assertOk()->assertSee('Live post')->assertDontSee('Secret draft');
        $this->get('/news/live')->assertOk();
        $this->get('/news/draft')->assertNotFound();
    }

    public function test_sitemap_lists_only_published_content(): void
    {
        $this->page();
        $this->page(['slug' => 'hidden', 'status' => 'draft']);

        $this->get('/sitemap.xml')->assertOk()->assertSee(url('about'))->assertDontSee(url('hidden'));
    }

    public function test_robots_blocks_everything_outside_production(): void
    {
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /');
    }

    public function test_security_headers_are_sent_on_pages_and_404s(): void
    {
        foreach (['/', '/does/not/exist'] as $path) {
            $response = $this->get($path);
            $response->assertHeader('X-Frame-Options', 'DENY');
            $response->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->assertStringContainsString("frame-ancestors 'none'", $response->headers->get('Content-Security-Policy'));
        }
    }
}
