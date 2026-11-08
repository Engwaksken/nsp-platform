<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.categories.index', ['categories' => Category::query()->withCount('posts')->orderBy('name')->get()]);
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
