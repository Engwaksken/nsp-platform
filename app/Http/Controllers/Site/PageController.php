<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Support\PageBlocks;
use Illuminate\View\View;

class PageController extends Controller
{
    public function home(): View
    {
        $page = Page::query()->published()->where('slug', 'home')->first();

        return view('site.page', [
            'page' => $page,
            'blocks' => PageBlocks::prepare($page?->blocks ?? []),
            'isHome' => true,
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
