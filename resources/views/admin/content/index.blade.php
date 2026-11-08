@extends('layouts.admin')
@section('title', $definition['plural'])

@section('content')
    <div class="page-head">
        <h1>{{ $definition['plural'] }}</h1>
        <a class="btn btn-primary" href="{{ route('admin.content.create', $definition['key']) }}">Add {{ $definition['singular'] }}</a>
    </div>
    <div class="table-wrap" tabindex="0" role="region" aria-label="Scrollable table">
        <table>
            <thead><tr><th scope="col">{{ $definition['fields'][$definition['title']]['label'] }}</th><th scope="col">Order</th><th scope="col">Visible</th><th scope="col">Actions</th></tr></thead>
            <tbody>
            @forelse ($items as $item)
                <tr>
                    <td>{{ $item->{$definition['title']} }}
                        @if ($definition['key'] === 'testimonials' && !$item->consent_confirmed)<br><span class="badge badge-draft">No consent recorded: hidden</span>@endif</td>
                    <td>{{ $item->sort_order }}</td>
                    <td><span class="badge badge-{{ $item->is_published ? 'on' : 'off' }}">{{ $item->is_published ? 'Yes' : 'No' }}</span></td>
                    <td class="actions">
                        <a class="btn btn-secondary btn-sm" href="{{ route('admin.content.edit', [$definition['key'], $item->id]) }}">Edit</a>
                        <form method="post" action="{{ route('admin.content.destroy', [$definition['key'], $item->id]) }}" data-confirm="Delete this {{ $definition['singular'] }}?">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="empty">Nothing added yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $items->links() }}
@endsection
