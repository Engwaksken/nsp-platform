@extends('layouts.public', [
    'pageTitle' => $post->seo_title ?: $post->title,
    'metaDescription' => $post->seo_description ?: $post->excerpt,
    'canonical' => route('news.show', $post->slug),
    'ogImage' => $post->cover?->url(),
])

@section('content')
    <article class="container section narrow">
        <p class="meta"><a href="{{ route('news.index') }}">News</a></p>
        <h1>{{ $post->title }}</h1>
        <p class="meta">
            <time datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->format('j F Y') }}</time>
            @if ($post->categories->isNotEmpty()) · {{ $post->categories->pluck('name')->join(', ') }} @endif
        </p>
        @if ($post->cover)
            <p><img src="{{ $post->cover->url() }}" alt="{{ $post->cover->alt_text }}"></p>
        @endif
        <div class="prose">{!! $bodyHtml !!}</div>
    </article>
@endsection
