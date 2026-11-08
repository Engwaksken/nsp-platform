<article class="card">
    @if ($post->cover)
        <div class="card-media"><img src="{{ $post->cover->url() }}" alt="{{ $post->cover->alt_text }}" loading="lazy"></div>
    @endif
    <h3><a href="{{ route('news.show', $post->slug) }}">{{ $post->title }}</a></h3>
    <p class="meta"><time datetime="{{ $post->published_at->toDateString() }}">{{ $post->published_at->format('j M Y') }}</time></p>
    @if ($post->excerpt)<p>{{ $post->excerpt }}</p>@endif
</article>
