@extends('layouts.public', [
    'pageTitle' => $isHome ? '' : ($page->seo_title ?: $page->title),
    'metaDescription' => $page?->seo_description ?: $page?->summary,
    'canonical' => $page ? $page->publicUrl() : url('/'),
    'ogImage' => $page?->ogImage?->url(),
])

@section('content')
    @if (!$page)
        {{-- No published home page yet: a plain welcome so a fresh install is not blank. --}}
        <section class="hero">
            <div class="container">
                <h1>{{ $brand['site_name'] }}</h1>
                @if ($brand['site_tagline'])<p class="lead">{{ $brand['site_tagline'] }}</p>@endif
            </div>
        </section>
    @else
        @if (!collect($blocks)->contains('type', 'hero'))
            <div class="container section">
                <h1>{{ $page->title }}</h1>
                @if ($page->summary)<p class="lead">{{ $page->summary }}</p>@endif
            </div>
        @endif
        @foreach ($blocks as $index => $block)
            @include('site.block', ['block' => $block, 'first' => $index === 0, 'pageTitle' => $page->title])
        @endforeach
    @endif
@endsection
