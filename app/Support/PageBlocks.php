<?php

namespace App\Support;

use App\Models\Faq;
use App\Models\Media;
use App\Models\Page;
use App\Models\Partner;
use App\Models\Post;
use App\Models\Testimonial;

/**
 * Page builder sections. Editors store an ordered list of
 * ['type' => ..., 'heading' => ..., 'body' => markdown, 'items' => text, 'link_label', 'link_url',
 *  'background' => ''|color|image|video, 'bg_color' => '#rrggbb', 'bg_media_id' => media id].
 */
class PageBlocks
{
    public const BACKGROUNDS = ['' => 'Default', 'color' => 'Colour', 'image' => 'Image', 'video' => 'Video'];

    /** FAQs shown in the FAQ call-to-action band (home page and FAQ sections). */
    public const FAQ_LIMIT = 3;

    /** Normalise editor input into storable blocks. */
    public static function fromInput(array $input): array
    {
        $blocks = [];
        foreach (array_values($input) as $block) {
            if (! is_array($block) || ! array_key_exists($block['type'] ?? '', Page::BLOCK_TYPES)) {
                continue;
            }
            $blocks[] = [
                'type' => $block['type'],
                'heading' => mb_substr(trim((string) ($block['heading'] ?? '')), 0, 200),
                'body' => mb_substr(trim((string) ($block['body'] ?? '')), 0, 20000),
                'items' => mb_substr(trim((string) ($block['items'] ?? '')), 0, 2000),
                'link_label' => mb_substr(trim((string) ($block['link_label'] ?? '')), 0, 80),
                'link_url' => self::safeUrl((string) ($block['link_url'] ?? '')),
            ] + self::backgroundInput($block);
        }

        return $blocks;
    }

    /** Keep only the background setting that matches the chosen type (the colour picker always posts a value). */
    private static function backgroundInput(array $block): array
    {
        $mode = (string) ($block['background'] ?? '');
        $mode = array_key_exists($mode, self::BACKGROUNDS) ? $mode : '';
        $color = preg_match('/^#[0-9a-f]{6}$/i', (string) ($block['bg_color'] ?? '')) ? strtolower($block['bg_color']) : '';
        $mediaId = ctype_digit((string) ($block['bg_media_id'] ?? '')) ? (int) $block['bg_media_id'] : null;

        return [
            'background' => $mode,
            'bg_color' => $mode === 'color' ? $color : '',
            'bg_media_id' => in_array($mode, ['image', 'video'], true) ? $mediaId : null,
        ];
    }

    /** Load what each block needs to render. */
    public static function prepare(array $blocks): array
    {
        // One query for every background image or video on the page.
        $ids = array_filter(array_map(fn ($block) => in_array($block['background'] ?? '', ['image', 'video'], true) ? ($block['bg_media_id'] ?? null) : null, $blocks));
        $media = $ids ? Media::query()->whereIn('id', $ids)->get()->keyBy('id') : collect();

        return array_map(function (array $block) use ($media) {
            $block['html'] = Markdown::render($block['body'] ?? '');
            $block['bg'] = self::background($block, $media);
            $block['data'] = match ($block['type'] ?? null) {
                'testimonials' => Testimonial::query()->visible()->with('photo')->limit(6)->get(),
                'partners' => Partner::query()->visible()->with('logo')->get(),
                'faqs' => Faq::query()->visible()->limit(self::FAQ_LIMIT)->get(),
                'latest_posts' => Post::query()->published()->latest('published_at')->limit(3)->get(),
                'stats' => self::parseStats($block['items'] ?? ''),
                default => null,
            };

            return $block;
        }, $blocks);
    }

    /**
     * How a section's background renders: extra classes, a style attribute (CSS custom properties only)
     * and an optional video. Null for the default look, or when the chosen file is missing or the wrong kind.
     * "on-dark" sections use light text; images and videos always get a dark overlay so text stays readable.
     */
    public static function background(array $block, $media): ?array
    {
        $mode = $block['background'] ?? '';
        $color = (string) ($block['bg_color'] ?? '');
        if ($mode === 'color' && preg_match('/^#[0-9a-f]{6}$/i', $color)) {
            return ['class' => 'has-bg has-bg-color '.(self::isDark($color) ? 'on-dark' : 'on-light'),
                'style' => '--section-bg: '.$color, 'video' => null, 'type' => null];
        }
        $file = in_array($mode, ['image', 'video'], true) ? $media->get($block['bg_media_id'] ?? 0) : null;
        if ($file && $mode === 'image' && $file->isImage()) {
            $url = str_replace(['"', '\\', "\n", "\r", ')'], '', $file->url());

            return ['class' => 'has-bg has-bg-image on-dark', 'style' => '--section-bg-image: url("'.$url.'")', 'video' => null, 'type' => null];
        }
        if ($file && $mode === 'video' && $file->isVideo()) {
            return ['class' => 'has-bg has-bg-video on-dark', 'style' => null, 'video' => $file->url(), 'type' => $file->mime_type];
        }

        return null;
    }

    /** True when white text reads better than dark text on this colour (WCAG relative luminance). */
    public static function isDark(string $hex): bool
    {
        [$r, $g, $b] = array_map(function ($c) {
            $c = hexdec($c) / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, str_split(ltrim($hex, '#'), 2));
        $luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;

        return (1.05 / ($luminance + 0.05)) >= (($luminance + 0.05) / 0.05);
    }

    /** "1,200 | Startups supported" per line. */
    public static function parseStats(string $items): array
    {
        $stats = [];
        foreach (preg_split('/\r\n|\r|\n/', $items) ?: [] as $line) {
            [$value, $label] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
            if ($value !== '' && $label !== '') {
                $stats[] = ['value' => $value, 'label' => $label];
            }
        }

        return $stats;
    }

    /** Allow site-relative paths and http(s) URLs only. */
    public static function safeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return mb_substr($url, 0, 500);
        }

        return preg_match('#^https?://#i', $url) && filter_var($url, FILTER_VALIDATE_URL) ? mb_substr($url, 0, 500) : '';
    }
}
