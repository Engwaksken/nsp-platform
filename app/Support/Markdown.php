<?php

namespace App\Support;

use Illuminate\Support\Str;

/** Renders editor-supplied Markdown safely: raw HTML is escaped and unsafe links are dropped. */
class Markdown
{
    public static function render(?string $text): string
    {
        if ($text === null || trim($text) === '') {
            return '';
        }

        return Str::markdown($text, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
        ]);
    }
}
