@extends('layouts.public', ['pageTitle' => 'Marketplace', 'metaDescription' => 'Products, services, requests to buy and partnership offers from startups in the Hive ecosystem.'])

@section('content')
    <div class="container section">
        <h1>Marketplace</h1>
        <p>Products and services from Hive startups, things they want to buy, and partnerships they are looking for.
            @auth <a href="{{ route('marketplace.inbox') }}">My messages</a>@endauth</p>

        @include('partials.stats', ['label' => 'Marketplace at a glance', 'stats' => \App\Support\PublicStats::marketplace()])

        <form method="get" class="filters" role="search" aria-label="Filter the marketplace">
            <div class="form-group"><label for="q">Keyword</label><input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search products and services…" maxlength="100"></div>
            <div class="form-group"><label for="kind">Type</label>
                <select id="kind" name="kind"><option value="">All</option>
                    @foreach (\App\Models\Marketplace\Listing::KINDS as $value => $label)<option value="{{ $value }}" @selected(($filters['kind'] ?? '') === $value)>{{ $label }}</option>@endforeach
                </select></div>
            <div class="form-group"><label for="category">Category</label>
                <select id="category" name="category"><option value="">All</option>
                    @foreach (\App\Models\Marketplace\Listing::categories() as $value => $label)<option value="{{ $value }}" @selected(($filters['category'] ?? '') === $value)>{{ $label }}</option>@endforeach
                </select></div>
            <button class="btn btn-secondary" type="submit">Search</button>
        </form>

        <div class="grid">
            @forelse ($listings as $listing)
                <article class="card">
                    <p><span class="badge">{{ \App\Models\Marketplace\Listing::KINDS[$listing->kind] }}</span></p>
                    <h2 class="h4"><a href="{{ route('marketplace.show', $listing) }}">{{ $listing->title }}</a></h2>
                    <p class="meta">{{ $listing->startup->display_name }} · {{ \App\Models\Marketplace\Listing::categories()[$listing->category] ?? $listing->category }}@if ($listing->location) · {{ $listing->location }}@endif</p>
                    <p>{{ $listing->summary }}</p>
                    @if ($listing->price_note)<p class="meta">{{ $listing->price_note }}</p>@endif
                </article>
            @empty
                <p class="empty">Nothing matches. Try fewer filters.</p>
            @endforelse
        </div>
        {{ $listings->links() }}
        <p class="hint">Hive does not take part in sales or check products. Agree terms directly, and report anything suspicious.</p>
    </div>
@endsection
