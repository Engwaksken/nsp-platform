@extends('layouts.admin')
@section('title', 'Menus')

@section('content')
    <div class="page-head"><h1>Menus</h1></div>
    @php($pageOptions = $pages->mapWithKeys(fn ($page) => [$page->id => $page->title])->all())

    @foreach ($menus as $menu)
        <section class="panel" aria-labelledby="menu-{{ $menu->id }}">
            <h2 id="menu-{{ $menu->id }}">{{ $menu->name }} menu</h2>
            <div class="table-wrap" tabindex="0" role="region" aria-label="Scrollable table">
                <table>
                    <thead><tr><th scope="col">Label</th><th scope="col">Page or link</th><th scope="col">Order</th><th scope="col">New tab</th><th scope="col">Actions</th></tr></thead>
                    <tbody>
                    @forelse ($menu->items as $item)
                        <tr>
                            <td colspan="4">
                                <form method="post" action="{{ route('admin.menus.items.update', [$menu, $item]) }}" class="form-row" id="item-{{ $item->id }}">
                                    @csrf @method('PUT')
                                    <div><label for="label-{{ $item->id }}" class="visually-hidden">Label</label><input id="label-{{ $item->id }}" type="text" name="label" value="{{ $item->label }}" required maxlength="80" placeholder="Menu label"></div>
                                    <div>
                                        <label for="page-{{ $item->id }}" class="visually-hidden">Page</label>
                                        <select id="page-{{ $item->id }}" name="page_id"><option value="">Custom link</option>
                                            @foreach ($pageOptions as $id => $title)<option value="{{ $id }}" @selected($item->page_id === $id)>{{ $title }}</option>@endforeach
                                        </select>
                                        <label for="url-{{ $item->id }}" class="visually-hidden">Link</label>
                                        <input id="url-{{ $item->id }}" type="text" name="url" value="{{ $item->url }}" placeholder="/path or https://…">
                                    </div>
                                    <div><label for="order-{{ $item->id }}" class="visually-hidden">Order</label><input id="order-{{ $item->id }}" type="number" name="sort_order" value="{{ $item->sort_order }}" min="0" max="999"></div>
                                    <div><label class="checkbox"><input type="checkbox" name="open_in_new_tab" value="1" @checked($item->open_in_new_tab)> <span>New tab</span></label></div>
                                </form>
                            </td>
                            <td class="actions">
                                <button type="submit" form="item-{{ $item->id }}" class="btn btn-secondary btn-sm">Save<span class="visually-hidden"> {{ $item->label }}</span></button>
                                <form method="post" action="{{ route('admin.menus.items.destroy', [$menu, $item]) }}" data-confirm="Remove this link?">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">Remove<span class="visually-hidden"> {{ $item->label }}</span></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty">No links in this menu.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <h3>Add a link</h3>
            <form method="post" action="{{ route('admin.menus.items.store', $menu) }}" class="form-row">
                @csrf
                <div><label for="new-label-{{ $menu->id }}">Label</label><input id="new-label-{{ $menu->id }}" type="text" name="label" required maxlength="80" placeholder="e.g. About us"></div>
                <div>
                    <label for="new-page-{{ $menu->id }}">Page</label>
                    <select id="new-page-{{ $menu->id }}" name="page_id"><option value="">Custom link</option>
                        @foreach ($pageOptions as $id => $title)<option value="{{ $id }}">{{ $title }}</option>@endforeach
                    </select>
                </div>
                <div><label for="new-url-{{ $menu->id }}">Or link</label><input id="new-url-{{ $menu->id }}" type="text" name="url" placeholder="/path or https://…"></div>
                <div><label for="new-order-{{ $menu->id }}">Order</label><input id="new-order-{{ $menu->id }}" type="number" name="sort_order" value="0" min="0" max="999"></div>
                <div><button type="submit" class="btn btn-primary">Add link</button></div>
            </form>
        </section>
    @endforeach
@endsection
