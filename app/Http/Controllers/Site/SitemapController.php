<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Post;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function sitemap(): Response
    {
        $urls = [['loc' => url('/'), 'lastmod' => null]];
        foreach (Page::query()->published()->where('slug', '!=', 'home')->get(['slug', 'updated_at']) as $page) {
            $urls[] = ['loc' => url($page->slug), 'lastmod' => $page->updated_at];
        }
        $urls[] = ['loc' => route('news.index'), 'lastmod' => null];
        foreach (Post::query()->published()->get(['slug', 'updated_at']) as $post) {
            $urls[] = ['loc' => route('news.show', $post->slug), 'lastmod' => $post->updated_at];
        }
        $urls[] = ['loc' => route('faqs'), 'lastmod' => null];
        $urls[] = ['loc' => route('contact'), 'lastmod' => null];

        return response()->view('site.sitemap', ['urls' => $urls])->header('Content-Type', 'application/xml');
    }

    public function robots(): Response
    {
        $lines = app()->environment('production')
            ? ['User-agent: *', 'Disallow: /admin', 'Disallow: /workspace', 'Disallow: /login', 'Sitemap: '.route('sitemap')]
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n")->header('Content-Type', 'text/plain');
    }
}
