@extends('layouts.public', ['pageTitle' => 'Startup directory', 'metaDescription' => 'Startups in the Hive ecosystem that chose to be listed.'])

@section('content')
    <div class="container section">
        <h1>Startup directory</h1>
        <p>Startups in the Hive ecosystem that chose to be listed. Each startup decides what appears here.</p>

        @include('partials.stats', ['label' => 'Directory at a glance', 'stats' => \App\Support\PublicStats::directory()])

        <form method="get" class="filters" role="search" aria-label="Filter startups">
            <div class="form-group"><label for="q">Name or keyword</label><input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search startups by name or keyword…" maxlength="100"></div>
            <div class="form-group"><label for="sector">Sector</label>
                <select id="sector" name="sector"><option value="">All</option>
                    @foreach (\App\Models\Registry\StartupProfile::SECTORS as $sector)<option value="{{ $sector }}" @selected(($filters['sector'] ?? '') === $sector)>{{ $sector }}</option>@endforeach
                </select></div>
            <div class="form-group"><label for="country">Country</label>
                <select id="country" name="country"><option value="">All</option>
                    @foreach (\App\Models\Registry\Startup::COUNTRIES as $code => $name)<option value="{{ $code }}" @selected(($filters['country'] ?? '') === $code)>{{ $name }}</option>@endforeach
                </select></div>
            <button class="btn btn-secondary" type="submit">Search</button>
        </form>

        <div class="grid">
            @forelse ($startups as $startup)
                <article class="card">
                    <h2 class="h4"><a href="{{ route('directory.show', $startup) }}">{{ $startup->display_name }}</a></h2>
                    <p class="meta">{{ $startup->profile?->sector ?: 'Sector not given' }}@if ($startup->profile?->headquarters_location) · {{ $startup->profile->headquarters_location }}@endif</p>
                    @if ($startup->description)<p>{{ \Illuminate\Support\Str::limit($startup->description, 160) }}</p>@endif
                </article>
            @empty
                <p class="empty">No startups match.</p>
            @endforelse
        </div>
        {{ $startups->links() }}
    </div>
@endsection
