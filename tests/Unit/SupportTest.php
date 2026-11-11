<?php

namespace Tests\Unit;

use App\Models\SeoRedirect;
use App\Support\Contrast;
use App\Support\FieldPlaceholder;
use App\Support\PageBlocks;
use App\Support\Settings;
use PHPUnit\Framework\TestCase;

class SupportTest extends TestCase
{
    public function test_contrast_ratio_matches_wcag_reference_values(): void
    {
        $this->assertEqualsWithDelta(21.0, Contrast::ratio('#000000', '#ffffff'), 0.01);
        $this->assertEqualsWithDelta(1.0, Contrast::againstWhite('#ffffff'), 0.01);
        $this->assertEqualsWithDelta(6.7, Contrast::againstWhite('#1d4ed8'), 0.1);
        $this->assertSame(0.0, Contrast::againstWhite('red'));
    }

    public function test_brand_tokens_keep_text_readable_in_light_and_dark_mode(): void
    {
        $this->assertSame('#ffffff', Contrast::textOn('#1e3a8a'));
        $this->assertSame(Contrast::DARK_TEXT, Contrast::textOn('#facc15'));
        $this->assertSame('#3a3a3c', Contrast::readable('#3a3a3c', '#ffffff'));
        $this->assertSame('#808080', Contrast::mix('#000000', '#ffffff', 0.5));

        $this->assertGreaterThanOrEqual(4.5, Contrast::ratio(Contrast::textOn('#777777'), '#777777'));

        foreach (['#3a3a3c', '#1e3a8a', '#facc15', '#000000', '#ffffff', '#f1592a', '#777777', '#0ea5e9'] as $colour) {
            $tokens = Contrast::brandTokens(['primary_color' => $colour, 'secondary_color' => $colour, 'accent_color' => $colour]);
            $this->assertGreaterThanOrEqual(4.5, Contrast::ratio($tokens['--brand-link-dark'], Contrast::DARK_SURFACE), $colour);
            $this->assertGreaterThanOrEqual(4.5, Contrast::ratio($tokens['--brand-accent-text-dark'], Contrast::DARK_SURFACE), $colour);
            $this->assertGreaterThanOrEqual(4.5, Contrast::ratio($tokens['--brand-link-light'], Contrast::LIGHT_SURFACE), $colour);
            $this->assertGreaterThanOrEqual(4.5, Contrast::ratio($tokens['--on-button-dark'], $tokens['--brand-button-dark']), $colour);
            $this->assertGreaterThanOrEqual(3.0, Contrast::ratio($tokens['--brand-button-dark'], Contrast::DARK_PAGE), $colour);
        }
        // Non-hex values (never saved by the settings form) add no tokens, so the CSS fallbacks apply.
        $this->assertSame([], Contrast::brandTokens(['primary_color' => 'red', 'secondary_color' => '#000000', 'accent_color' => '#000000']));
    }

    public function test_default_branding_uses_hive_palette_with_contrast_safe_primary(): void
    {
        $this->assertSame('#3a3a3c', Settings::DEFAULTS['primary_color']);
        $this->assertSame('#f7941d', Settings::DEFAULTS['secondary_color']);
        $this->assertSame('#f1592a', Settings::DEFAULTS['accent_color']);
        $this->assertGreaterThanOrEqual(7, Contrast::againstWhite(Settings::DEFAULTS['primary_color']));
        $this->assertGreaterThanOrEqual(4.5, Contrast::ratio(Settings::DEFAULTS['primary_color'], Settings::DEFAULTS['secondary_color']));
    }

    public function test_safe_url_allows_only_site_paths_and_http_links(): void
    {
        $this->assertSame('/contact', PageBlocks::safeUrl('/contact'));
        $this->assertSame('https://hive.test/a', PageBlocks::safeUrl('https://hive.test/a'));
        $this->assertSame('', PageBlocks::safeUrl('javascript:alert(1)'));
        $this->assertSame('', PageBlocks::safeUrl('//evil.test'));
        $this->assertSame('', PageBlocks::safeUrl('data:text/html,hi'));
    }

    public function test_stats_lines_are_parsed(): void
    {
        $this->assertSame(
            [['value' => '120', 'label' => 'Startups'], ['value' => '35%', 'label' => 'Women-led']],
            PageBlocks::parseStats("120 | Startups\nbroken line\n35% | Women-led\n")
        );
    }

    public function test_unknown_block_types_are_dropped(): void
    {
        $blocks = PageBlocks::fromInput([['type' => 'text', 'heading' => 'Hi'], ['type' => 'script'], 'not-an-array']);

        $this->assertCount(1, $blocks);
        $this->assertSame('Hi', $blocks[0]['heading']);
    }

    public function test_redirect_paths_are_normalised(): void
    {
        $this->assertSame('/old-page', SeoRedirect::normalizePath('/Old-Page/?utm=1'));
        $this->assertSame('/', SeoRedirect::normalizePath(''));
    }

    public function test_field_placeholders_are_derived_from_label_and_type(): void
    {
        $this->assertSame('Enter title', FieldPlaceholder::for('Title'));
        $this->assertSame('Enter SEO title', FieldPlaceholder::for('SEO title'));
        $this->assertSame('Enter pay', FieldPlaceholder::for('Pay (optional)'));
        $this->assertSame('name@example.com', FieldPlaceholder::for('Email address', 'email'));
        $this->assertSame('https://', FieldPlaceholder::for('Where to apply (link)', 'url'));
        $this->assertSame('Enter meeting link or place', FieldPlaceholder::for('Meeting link or place'));
        $this->assertSame('e.g. my-page-name', FieldPlaceholder::for('URL slug'));
        $this->assertSame('Separate items with commas', FieldPlaceholder::for('Tags (comma separated)'));
        $this->assertSame('Enter your password', FieldPlaceholder::for('Password', 'password'));
        $this->assertSame('Type your answer here…', FieldPlaceholder::for('What happened?', 'textarea'));
        $this->assertSame('Write about you…', FieldPlaceholder::for('About you', 'textarea'));
        $this->assertSame('Enter details…', FieldPlaceholder::for('Details', 'textarea'));
        $this->assertNull(FieldPlaceholder::for('Publication date', 'date'));
        $this->assertNull(FieldPlaceholder::for('Category', 'select'));
    }
}
