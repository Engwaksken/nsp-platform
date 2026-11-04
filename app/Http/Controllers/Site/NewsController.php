<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Support\Markdown;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NewsController extends Controller
{
    public function index(Request $request): View
    {
        $category = $request->filled('category')
            ? Category::query()->where('slug', (string) $request->query('category'))->first()
            : null;

        $posts = Post::query()->published()
            ->with(['cover', 'categories'])
            ->when($category, fn ($query) => $query->whereHas('categories', fn ($q) => $q->whereKey($category->id)))
            ->latest('published_at')
            ->paginate(9)
            ->withQueryString();

        return view('site.news.index', [
            'posts' => $posts,
            'categories' => Category::query()->orderBy('name')->get(),
            'activeCategory' => $category,
        ]);
    }

    public function show(string $slug): View
    {
        $post = Post::query()->published()->with(['cover', 'categories', 'author'])->where('slug', $slug)->firstOrFail();

        return view('site.news.show', ['post' => $post, 'bodyHtml' => Markdown::render($post->body)]);
    }
}
