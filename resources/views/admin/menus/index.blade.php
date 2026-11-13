@extends('layouts.admin')
@section('title', 'Menus')

@section('content')
    <div class="page-head">
        <div>
            <h1>Menus</h1>
            <p>Links shown in the website header and footer. Lower order numbers show first.</p>
        </div>
    </div>
    @php($pageOptions = $pages->mapWithKeys(fn ($page) => [$page->id => $page->title])->all())

    <div data-tabs="Menus" data-tabs-key="menus">
    @foreach ($menus as $menu)
        <section class="panel" data-tab="{{ $menu->name }}" data-tab-count="{{ $menu->items->count() }}" aria-labelledby="menu-{{ $menu->id }}">
            <div class="page-head">
                <h2 id="menu-{{ $menu->id }}">{{ $menu->name }} menu</h2>
                <div class="actions" id="menu-actions-{{ $menu->id }}"></div>
            </div>
            <div class="table-wrap" tabindex="0" role="region" aria-label="{{ $menu->name }} menu links">
                <table>
                    <thead><tr><th scope="col">Label</th><th scope="col">Goes to</th><th scope="col">Order</th><th scope="col">Opens in</th><th scope="col">Actions</th></tr></thead>
                    <tbody>
                    @forelse ($menu->items as $item)
                        <tr>
                            <td><strong>{{ $item->label }}</strong></td>
                            <td>
                                @if ($item->page_id && isset($pageOptions[$item->page_id]))
                                    <span class="badge">Page</span> {{ $pageOptions[$item->page_id] }}
                                @else
                                    <span class="badge">Link</span> <code>{{ $item->url }}</code>
                                @endif
                            </td>
                            <td>{{ $item->sort_order }}</td>
                            <td>{{ $item->open_in_new_tab ? 'New tab' : 'Same tab' }}</td>
                            <td><div class="icon-actions">
                                <a class="btn btn-icon" href="#edit-item-{{ $item->id }}" data-modal-open="edit-item-{{ $item->id }}" data-tip="Edit"><i class="fas fa-pen-to-square" aria-hidden="true"></i><span class="visually-hidden">Edit {{ $item->label }}</span></a>
                                <form method="post" action="{{ route('admin.menus.items.destroy', [$menu, $item]) }}" data-confirm="Remove “{{ $item->label }}” from the {{ strtolower($menu->name) }} menu?">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-icon btn-icon-danger" data-tip="Remove"><i class="fas fa-trash-can" aria-hidden="true"></i><span class="visually-hidden">Remove {{ $item->label }}</span></button>
                                </form>
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty">No links in this menu yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Without JavaScript these forms show below the table; with it they open as popups. --}}
            @foreach ($menu->items as $item)
                <div class="panel" id="edit-item-{{ $item->id }}" data-inline-modal data-modal-title="Edit link: {{ $item->label }}">
                    <h3>Edit link: {{ $item->label }}</h3>
                    <form method="post" action="{{ route('admin.menus.items.update', [$menu, $item]) }}">
                        @csrf @method('PUT')
                        @include('admin.menus.item-fields', ['prefix' => 'item-'.$item->id, 'item' => $item, 'pageOptions' => $pageOptions])
                        <div class="actions">
                            <button type="submit" class="btn btn-primary">Save link</button>
                        </div>
                    </form>
                </div>
            @endforeach

            <div class="panel" id="add-item-{{ $menu->id }}" data-inline-modal data-modal-trigger="Add link" data-modal-trigger-class="btn btn-primary btn-sm" data-modal-trigger-into="#menu-actions-{{ $menu->id }}">
                <h3>Add a link to the {{ strtolower($menu->name) }} menu</h3>
                <form method="post" action="{{ route('admin.menus.items.store', $menu) }}">
                    @csrf
                    @include('admin.menus.item-fields', ['prefix' => 'new-'.$menu->id, 'item' => null, 'pageOptions' => $pageOptions])
                    <div class="actions">
                        <button type="submit" class="btn btn-primary">Add link</button>
                    </div>
                </form>
            </div>
        </section>
    @endforeach
    </div>
@endsection
