@extends('layouts.admin')
@section('title', 'News')

@section('content')
    <div class="page-head">
        <h1>News</h1>
        <a class="btn btn-primary" href="{{ route('admin.posts.create') }}" data-modal="lg">New post</a>
    </div>
    <form method="get" class="filters" role="search">
        <div class="form-group"><label for="q">Search</label><input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Search news by title…"></div>
        <button class="btn btn-secondary" type="submit">Search</button>
    </form>
    <div class="table-wrap" tabindex="0" role="region" aria-label="Scrollable table">
        <table>
            <thead><tr><th scope="col">Title</th><th scope="col">Status</th><th scope="col">Publish date</th><th scope="col">Actions</th></tr></thead>
            <tbody>
            @forelse ($posts as $post)
                <tr>
                    <td><a href="{{ route('admin.posts.edit', $post) }}" data-modal="lg">{{ $post->title }}</a></td>
                    <td><span class="badge badge-{{ $post->displayStatus() }}">{{ ucfirst($post->displayStatus()) }}</span></td>
                    <td>{{ $post->published_at?->format('j M Y H:i') ?? '—' }}</td>
                    <td class="actions">
                        <a class="btn btn-secondary btn-sm" href="{{ route('admin.posts.edit', $post) }}" data-modal="lg">Edit<span class="visually-hidden"> {{ $post->title }}</span></a>
                        @if ($post->isLive())<a class="btn btn-secondary btn-sm" href="{{ route('news.show', $post->slug) }}" target="_blank" rel="noopener">View</a>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="empty">No posts yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $posts->links() }}
@endsection
