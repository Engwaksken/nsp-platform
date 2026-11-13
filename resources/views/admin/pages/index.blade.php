@extends('layouts.admin')
@section('title', 'Pages')

@section('content')
    <div class="page-head">
        <h1>Pages</h1>
        <a class="btn btn-primary" href="{{ route('admin.pages.create') }}" data-modal="lg">New page</a>
    </div>
    @include('partials.stats', ['label' => 'Summary', 'stats' => \App\Support\AdminStats::pages()])

    <form method="get" class="filters" role="search">
        <div class="form-group"><label for="q">Search</label><input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Search pages by title…"></div>
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

    <div class="table-wrap" tabindex="0" role="region" aria-label="Scrollable table">
        <table>
            <caption class="visually-hidden">Pages</caption>
            <thead><tr><th scope="col">Title</th><th scope="col">Address</th><th scope="col">Status</th><th scope="col">Updated</th><th scope="col">Actions</th></tr></thead>
            <tbody>
            @forelse ($pages as $page)
                <tr>
                    <td><a href="{{ route('admin.pages.edit', $page) }}" data-modal="lg">{{ $page->title }}</a></td>
                    <td>/{{ $page->slug === 'home' ? '' : $page->slug }}</td>
                    <td><span class="badge badge-{{ $page->displayStatus() }}">{{ ucfirst($page->displayStatus()) }}</span>
                        @if ($page->displayStatus() === 'scheduled')<br><span class="meta">{{ $page->published_at->format('j M Y H:i') }}</span>@endif</td>
                    <td>{{ $page->updated_at->diffForHumans() }}</td>
                    <td><div class="icon-actions">
                        <a class="btn btn-icon" href="{{ route('admin.pages.edit', $page) }}" data-modal="lg" data-tip="Edit"><i class="fas fa-pen-to-square" aria-hidden="true"></i><span class="visually-hidden">Edit {{ $page->title }}</span></a>
                        <a class="btn btn-icon" href="{{ route('admin.pages.preview', $page) }}" target="_blank" rel="noopener" data-tip="Preview"><i class="fas fa-eye" aria-hidden="true"></i><span class="visually-hidden">Preview {{ $page->title }} (opens in a new tab)</span></a>
                    </div></td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">No pages found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $pages->links() }}
@endsection
