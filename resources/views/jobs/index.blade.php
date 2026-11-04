@extends('layouts.public', ['pageTitle' => 'Jobs at Hive startups', 'metaDescription' => 'Jobs, internships, gigs and volunteer roles at startups in the Hive ecosystem.'])

@section('content')
    <div class="container section">
        <h1>Jobs at Hive startups</h1>
        <p>Jobs, internships, short gigs and volunteer roles posted by startups on Hive NSP.
            @auth <a href="{{ route('talent.dashboard') }}">My applications</a> · <a href="{{ route('talent.profile') }}">My candidate profile</a>@else <a href="{{ route('login') }}">Sign in</a> to apply.@endauth</p>

        <form method="get" class="filters" role="search" aria-label="Filter jobs">
            <div class="form-group"><label for="q">Keyword or skill</label><input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100"></div>
            <div class="form-group"><label for="type">Type</label>
                <select id="type" name="type"><option value="">Any</option>
                    @foreach (\App\Models\Talent\Vacancy::TYPES as $value => $label)<option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>@endforeach
                </select></div>
            <div class="form-group"><label for="sector">Sector</label>
                <select id="sector" name="sector"><option value="">Any</option>
                    @foreach (\App\Models\Registry\StartupProfile::SECTORS as $sector)<option value="{{ $sector }}" @selected(($filters['sector'] ?? '') === $sector)>{{ $sector }}</option>@endforeach
                </select></div>
            <label class="checkbox"><input type="checkbox" name="remote" value="1" @checked(request()->boolean('remote'))> Remote possible</label>
            <button class="btn btn-secondary" type="submit">Search</button>
        </form>

        <div class="grid">
            @forelse ($vacancies as $vacancy)
                <article class="card">
                    <h2 class="h4"><a href="{{ route('jobs.show', $vacancy) }}">{{ $vacancy->title }}</a></h2>
                    <p class="meta">{{ $vacancy->startup->display_name }} · {{ \App\Models\Talent\Vacancy::TYPES[$vacancy->type] }}
                        · {{ $vacancy->location ?: (\App\Models\Registry\Startup::COUNTRIES[$vacancy->startup->country] ?? '') }}@if ($vacancy->remote) · Remote possible @endif</p>
                    <p>{{ \Illuminate\Support\Str::limit($vacancy->description, 180) }}</p>
                    <p class="meta">@if ($vacancy->closes_on)Apply by {{ $vacancy->closes_on->format('j M Y') }}@else Open until filled @endif</p>
                </article>
            @empty
                <p class="empty">No open roles match. Try fewer filters.</p>
            @endforelse
        </div>
        {{ $vacancies->links() }}
    </div>
@endsection
