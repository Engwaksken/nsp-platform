@extends('layouts.admin')
@section('title', 'Pages')

@section('content')
    <div class="page-head">
        <h1>Pages</h1>
        <a class="btn btn-primary" href="{{ route('admin.pages.create') }}">New page</a>
    </div>

    <form method="get" class="filters" role="search">
        <div class="form-group"><label for="q">Search</label><input id="q" type="search" name="q" value="{{ request('q') }}"></div>
        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">All</option>
                @foreach (\App\Models\Page::STATUSES as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-secondary" type="submit">Filter</button>
    </form>

    <div class="table-wrap">
        <table>
            <caption class="visually-hidden">Pages</caption>
            <thead><tr><th scope="col">Title</th><th scope="col">Address</th><th scope="col">Status</th><th scope="col">Updated</th><th scope="col">Actions</th></tr></thead>
            <tbody>
            @forelse ($pages as $page)
                <tr>
                    <td><a href="{{ route('admin.pages.edit', $page) }}">{{ $page->title }}</a></td>
                    <td>/{{ $page->slug === 'home' ? '' : $page->slug }}</td>
                    <td><span class="badge badge-{{ $page->displayStatus() }}">{{ ucfirst($page->displayStatus()) }}</span>
                        @if ($page->displayStatus() === 'scheduled')<br><span class="meta">{{ $page->published_at->format('j M Y H:i') }}</span>@endif</td>
                    <td>{{ $page->updated_at->diffForHumans() }}</td>
                    <td class="actions">
                        <a class="btn btn-secondary btn-sm" href="{{ route('admin.pages.edit', $page) }}">Edit<span class="visually-hidden"> {{ $page->title }}</span></a>
                        <a class="btn btn-secondary btn-sm" href="{{ route('admin.pages.preview', $page) }}" target="_blank" rel="noopener">Preview</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">No pages found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $pages->links() }}
@endsection
