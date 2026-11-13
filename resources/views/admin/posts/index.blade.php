@extends('layouts.admin')
@section('title', 'News')

@section('content')
    <div class="page-head">
        <h1>News</h1>
        <a class="btn btn-primary" href="{{ route('admin.posts.create') }}" data-modal="lg">New post</a>
    </div>
    @include('partials.stats', ['label' => 'Summary', 'stats' => \App\Support\AdminStats::posts()])
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
                    <td>@if ($post->published_at){{ $post->published_at->format('j M Y H:i') }}@else<span class="meta">Not published</span>@endif</td>
                    <td><div class="icon-actions">
                        <a class="btn btn-icon" href="{{ route('admin.posts.edit', $post) }}" data-modal="lg" data-tip="Edit"><i class="fas fa-pen-to-square" aria-hidden="true"></i><span class="visually-hidden">Edit {{ $post->title }}</span></a>
                        @if ($post->isLive())<a class="btn btn-icon" href="{{ route('news.show', $post->slug) }}" target="_blank" rel="noopener" data-tip="View"><i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i><span class="visually-hidden">View {{ $post->title }} (opens in a new tab)</span></a>@endif
                    </div></td>
                </tr>
            @empty
                <tr><td colspan="4" class="empty">No posts yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $posts->links() }}
@endsection
