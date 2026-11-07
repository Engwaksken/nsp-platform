<?php

namespace App\Http\Controllers\Knowledge\Admin;

use App\Http\Controllers\Controller;
use App\Models\Knowledge\KnowledgeCategory;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Hierarchical Knowledge Hub taxonomy. */
class CategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.knowledge.categories', ['categories' => KnowledgeCategory::tree()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $category = KnowledgeCategory::query()->create($this->validated($request));
        Audit::log('knowledge.category_created', $category, ['name' => $category->name]);

        return back()->with('status', 'Category added.');
    }

    public function update(Request $request, KnowledgeCategory $category): RedirectResponse
    {
        $data = $this->validated($request, $category);
        if (! empty($data['parent_id']) && in_array((int) $data['parent_id'], $category->selfAndDescendantIds(), true)) {
            return back()->withErrors(['parent_id' => 'A category cannot sit inside itself or one of its sub-categories.']);
        }
        $category->update($data);
        Audit::log('knowledge.category_updated', $category, ['name' => $category->name]);

        return back()->with('status', 'Category saved.');
    }

    public function destroy(KnowledgeCategory $category): RedirectResponse
    {
        if ($category->resources()->exists() || $category->children()->exists()) {
            return back()->withErrors(['category' => 'Move its resources and sub-categories first.']);
        }
        $category->delete();
        Audit::log('knowledge.category_deleted', $category, ['name' => $category->name]);

        return back()->with('status', 'Category deleted.');
    }

    private function validated(Request $request, ?KnowledgeCategory $category = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('knowledge_categories', 'slug')->ignore($category?->id)],
            'parent_id' => ['nullable', 'integer', 'exists:knowledge_categories,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], ['slug.regex' => 'Use lower-case letters, numbers and single hyphens only.']);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }
}
