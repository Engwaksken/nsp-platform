<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Support\Audit;
use App\Support\PageBlocks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MenuController extends Controller
{
    public function index(): View
    {
        foreach (Menu::LOCATIONS as $location => $name) {
            Menu::query()->firstOrCreate(['location' => $location], ['name' => $name]);
        }

        return view('admin.menus.index', [
            'menus' => Menu::query()->with('items.page')->get(),
            'pages' => Page::query()->orderBy('title')->get(['id', 'title', 'slug']),
        ]);
    }

    public function storeItem(Request $request, Menu $menu): RedirectResponse
    {
        $item = $menu->items()->create($this->validated($request));
        Audit::log('menu.item_added', $menu, ['label' => $item->label]);

        return back()->with('status', 'Menu link added.');
    }

    public function updateItem(Request $request, Menu $menu, MenuItem $item): RedirectResponse
    {
        $item->update($this->validated($request));
        Audit::log('menu.item_updated', $menu, ['label' => $item->label]);

        return back()->with('status', 'Menu link saved.');
    }

    public function destroyItem(Menu $menu, MenuItem $item): RedirectResponse
    {
        $item->delete();
        Audit::log('menu.item_removed', $menu, ['label' => $item->label]);

        return back()->with('status', 'Menu link removed.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'page_id' => ['nullable', 'integer', 'exists:pages,id'],
            'url' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'open_in_new_tab' => ['nullable', 'boolean'],
        ]);
        $data['url'] = PageBlocks::safeUrl((string) ($data['url'] ?? ''));
        if (empty($data['page_id']) && $data['url'] === '') {
            throw ValidationException::withMessages(['url' => 'Choose a page, or enter a link starting with / or https://.']);
        }
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['open_in_new_tab'] = $request->boolean('open_in_new_tab');

        return $data;
    }
}
