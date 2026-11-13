@extends('layouts.public', [
    'pageTitle' => $isHome ? '' : ($page->seo_title ?: $page->title),
    'metaDescription' => $page?->seo_description ?: $page?->summary,
    'canonical' => $page ? $page->publicUrl() : url('/'),
    'ogImage' => $page?->ogImage?->url(),
])

@section('content')
    @if (!$page)
        {{-- No published home page yet: a welcome with the platform's live sections, so a fresh install is not blank. --}}
        @php
            $explore = collect([
                ['opportunities', 'opportunities.index', 'fa-bullhorn', 'Opportunities and events', 'Grants, accelerators, competitions and events for startups.'],
                ['marketplace', 'directory.index', 'fa-building-flag', 'Startup directory', 'Discover startups that chose to be listed.'],
                ['marketplace', 'marketplace.index', 'fa-store', 'Marketplace', 'Products, services and partnerships from the ecosystem.'],
                ['talent', 'jobs.index', 'fa-briefcase', 'Jobs', 'Roles at startups in the ecosystem.'],
                ['challenges', 'challenges.index', 'fa-lightbulb', 'Challenge Exchange', 'Innovation and market challenges to solve.'],
                ['knowledge', 'knowledge.index', 'fa-book-open', 'Knowledge Hub', 'Research, policy briefs, templates and toolkits.'],
            ])->filter(fn ($item) => \App\Support\Features::enabled($item[0]) && \Illuminate\Support\Facades\Route::has($item[1]));
            $dashboardUrl = auth()->check() ? (auth()->user()->canUseAdmin() ? route('admin.dashboard') : route('workspace')) : null;
        @endphp
        <section class="hero hero-home">
            <div class="container">
                <span class="hero-eyebrow">Startup ecosystem platform</span>
                <h1>{{ $brand['site_name'] }}</h1>
                @if ($brand['site_tagline'])<p class="lead">{{ $brand['site_tagline'] }}</p>@endif
                <p class="hero-actions">
                    @if ($dashboardUrl)
                        <a class="btn btn-primary" href="{{ $dashboardUrl }}">Go to dashboard</a>
                    @else
                        <a class="btn btn-primary" href="{{ route('login') }}">Sign in to get started</a>
                    @endif
                    @if ($explore->isNotEmpty())
                        <a class="btn btn-secondary" href="{{ route($explore->first()[1]) }}">Explore {{ strtolower($explore->first()[3]) }}</a>
                    @endif
                </p>
            </div>
        </section>
        @if ($explore->isNotEmpty())
            <section class="section" aria-labelledby="explore-heading">
                <div class="container">
                    <div class="section-intro">
                        <h2 id="explore-heading">What you can do here</h2>
                        <p>One place for founders, investors, experts, talent and partners.</p>
                    </div>
                    <div class="grid">
                        @foreach ($explore as [$flag, $route, $icon, $title, $text])
                            <a class="card card-link explore-card" href="{{ route($route) }}">
                                <span class="explore-icon" aria-hidden="true"><i class="fas {{ $icon }}"></i></span>
                                <h3>{{ $title }}</h3>
                                <p>{{ $text }}</p>
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif
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

    @if ($isHome && ($homeFaqs ?? collect())->isNotEmpty())
        @include('site.faq-cta', ['faqs' => $homeFaqs])
    @endif
@endsection
