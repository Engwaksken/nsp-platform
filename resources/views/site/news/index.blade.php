@extends('layouts.public', ['pageTitle' => $activeCategory ? 'News: ' . $activeCategory->name : 'News', 'canonical' => route('news.index')])

@section('content')
    <div class="container section">
        <h1>News{{ $activeCategory ? ': ' . $activeCategory->name : '' }}</h1>
        @if ($categories->isNotEmpty())
            <nav aria-label="News categories">
                <ul class="tags">
                    <li><a class="tag" href="{{ route('news.index') }}" @if (!$activeCategory) aria-current="true" @endif>All</a></li>
                    @foreach ($categories as $category)
                        <li><a class="tag" href="{{ route('news.index', ['category' => $category->slug]) }}"
                               @if ($activeCategory?->is($category)) aria-current="true" @endif>{{ $category->name }}</a></li>
                    @endforeach
                </ul>
            </nav>
        @endif

        @if ($posts->isEmpty())
            <p class="empty">No news has been published yet.</p>
        @else
            <div class="card-grid">
                @foreach ($posts as $post)
                    @include('site.news.card', ['post' => $post])
                @endforeach
            </div>
            {{ $posts->links() }}
        @endif
    </div>
@endsection
