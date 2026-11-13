<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\Page;
use App\Support\PageBlocks;
use Illuminate\View\View;

class PageController extends Controller
{
    public function home(): View
    {
        $page = Page::query()->published()->where('slug', 'home')->first();
        $blocks = PageBlocks::prepare($page?->blocks ?? []);

        // The home page always ends with common questions and a way to the FAQs page,
        // unless the editor already placed an FAQ section on it.
        $homeFaqs = collect($blocks)->contains('type', 'faqs')
            ? collect()
            : Faq::query()->visible()->limit(PageBlocks::FAQ_LIMIT)->get();

        return view('site.page', [
            'page' => $page,
            'blocks' => $blocks,
            'isHome' => true,
            'homeFaqs' => $homeFaqs,
        ]);
    }

    public function show(string $slug): View
    {
        abort_if($slug === 'home', 404);
        $page = Page::query()->published()->where('slug', $slug)->firstOrFail();

        return view('site.page', [
            'page' => $page,
            'blocks' => PageBlocks::prepare($page->blocks ?? []),
            'isHome' => false,
        ]);
    }
}
