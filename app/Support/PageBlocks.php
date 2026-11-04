<?php

namespace App\Support;

use App\Models\Faq;
use App\Models\Page;
use App\Models\Partner;
use App\Models\Post;
use App\Models\Testimonial;

/**
 * Page builder sections. Editors store an ordered list of
 * ['type' => ..., 'heading' => ..., 'body' => markdown, 'items' => text, 'link_label', 'link_url'].
 */
class PageBlocks
{
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
            ];
        }

        return $blocks;
    }

    /** Load what each block needs to render. */
    public static function prepare(array $blocks): array
    {
        return array_map(function (array $block) {
            $block['html'] = Markdown::render($block['body'] ?? '');
            $block['data'] = match ($block['type'] ?? null) {
                'testimonials' => Testimonial::query()->visible()->with('photo')->limit(6)->get(),
                'partners' => Partner::query()->visible()->with('logo')->get(),
                'faqs' => Faq::query()->visible()->limit(8)->get(),
                'latest_posts' => Post::query()->published()->latest('published_at')->limit(3)->get(),
                'stats' => self::parseStats($block['items'] ?? ''),
                default => null,
            };

            return $block;
        }, $blocks);
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
