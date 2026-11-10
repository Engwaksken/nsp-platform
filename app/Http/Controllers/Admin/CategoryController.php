<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $query = Category::query()->withCount('posts')->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->query('q').'%'));
        $total = Category::query()->count();
        $posts = Post::query()->whereHas('categories')->count();

        return view('admin.categories.index', [
            'categories' => $query->orderBy('name')->paginate(25)->withQueryString(),
            'categoryStats' => ['total' => $total, 'posts' => $posts],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80']]);
        $slug = Str::slug($data['name']);
        $request->merge(['slug' => $slug])->validate(['slug' => ['required', 'unique:categories,slug']], ['slug.unique' => 'A category with this name already exists.']);
        $category = Category::query()->create(['name' => $data['name'], 'slug' => $slug]);
        Audit::log('category.created', $category);

        return back()->with('status', 'Category added.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $category->delete();
        Audit::log('category.deleted', $category);

        return back()->with('status', 'Category deleted.');
    }
}
