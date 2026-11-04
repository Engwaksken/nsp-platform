<?php

namespace Tests\Unit;

use App\Models\SeoRedirect;
use App\Support\Contrast;
use App\Support\PageBlocks;
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
}
