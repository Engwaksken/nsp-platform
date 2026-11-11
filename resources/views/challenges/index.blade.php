@extends('layouts.browse', ['pageTitle' => 'Challenge Exchange', 'metaDescription' => 'Reviewed innovation and market challenges that startups can help solve.', 'canonical' => route('challenges.index')])

@section('content')
    <div class="container section">
        <div class="page-head">
            <h1>Challenge Exchange</h1>
            @auth
                <div class="actions">
                    <a class="btn btn-secondary" href="{{ route('challenges.mine') }}">My challenges &amp; proposals</a>
                    <a class="btn btn-primary" href="{{ route('challenges.create') }}">Submit a challenge</a>
                </div>
            @endauth
        </div>
        @include('partials.flash')
        <p>Innovation and market challenges from government, industry, partners and communities. Every challenge listed here was reviewed by Hive before publication; only sources marked as reviewed have been checked.</p>

        @include('partials.stats-summary', ['label' => 'Challenges at a glance', 'stats' => \App\Support\PublicStats::challenges()])

        <form method="get" class="filters" role="search" aria-label="Search and filter challenges">
            <div class="form-group"><label for="q">Search</label><input id="q" type="search" name="q" value="{{ $criteria['q'] }}" placeholder="Search challenges…" maxlength="100"></div>
            <div class="form-group"><label for="sector">Sector</label>
                <select id="sector" name="sector"><option value="">Any sector</option>
                    @foreach (\App\Models\Registry\StartupProfile::SECTORS as $option)<option value="{{ $option }}" @selected($criteria['sector'] === $option)>{{ $option }}</option>@endforeach
                </select></div>
            <div class="form-group"><label for="country">Location</label>
                <select id="country" name="country"><option value="">Anywhere</option>
                    @foreach (\App\Models\Registry\Startup::COUNTRIES as $code => $label)<option value="{{ $code }}" @selected($criteria['country'] === $code)>{{ $label }}</option>@endforeach
                </select></div>
            <div class="form-group"><label for="priority">Priority</label>
                <select id="priority" name="priority"><option value="">Any priority</option>
                    @foreach (config('challenges.priorities') as $value => $label)<option value="{{ $value }}" @selected($criteria['priority'] === $value)>{{ $label }}</option>@endforeach
                </select></div>
            <div class="form-group"><label for="issuer_type">Issuer</label>
                <select id="issuer_type" name="issuer_type"><option value="">Any issuer</option>
                    @foreach (config('challenges.issuer_types') as $value => $label)<option value="{{ $value }}" @selected($criteria['issuer_type'] === $value)>{{ $label }}</option>@endforeach
                </select></div>
            <div class="form-group"><label for="category">Category</label>
                <select id="category" name="category"><option value="">Any category</option>
                    @foreach (config('challenges.categories') as $value => $label)<option value="{{ $value }}" @selected($criteria['category'] === $value)>{{ $label }}</option>@endforeach
                </select></div>
            <div class="form-group"><label for="sort">Sort by</label>
                <select id="sort" name="sort">
                    @foreach (\App\Challenges\ChallengeSearch::SORTS as $value => $label)<option value="{{ $value }}" @selected($criteria['sort'] === $value)>{{ $label }}</option>@endforeach
                </select></div>
            <button type="submit" class="btn btn-secondary">Search</button>
        </form>

        <p class="meta" role="status">{{ $challenges->total() }} {{ \Illuminate\Support\Str::plural('challenge', $challenges->total()) }} found.</p>

        @if ($challenges->isEmpty())
            <p class="empty">No published challenges match. Try fewer filters.</p>
        @else
            <div class="card-grid">
                @foreach ($challenges as $challenge)
                    @php($v = $challenge->publishedVersion)
                    <article class="card">
                        <p class="meta">{{ config('challenges.categories')[$v->category] ?? 'Challenge' }}@if ($v->sector) · {{ $v->sector }}@endif</p>
                        <h2 class="h3"><a href="{{ route('challenges.show', $challenge) }}">{{ $v->title }}</a></h2>
                        <p>{{ \Illuminate\Support\Str::limit($v->problem_description, 220) }}</p>
                        <p class="meta">
                            <span class="badge badge-{{ in_array($v->priority, ['high', 'critical'], true) ? 'draft' : 'off' }}">{{ config('challenges.priorities')[$v->priority] ?? $v->priority }} priority</span>
                            @if ($v->country) · {{ \App\Models\Registry\Startup::COUNTRIES[$v->country] ?? $v->country }}@endif
                            @if ($v->issuer_name) · {{ $v->issuer_name }}@endif
                            @if ($v->visibility === 'members') · <span class="badge badge-scheduled">Members only</span>@endif
                        </p>
                        @if (in_array($challenge->id, $bookmarked, true))<span class="badge badge-on">Saved</span>@endif
                    </article>
                @endforeach
            </div>
            {{ $challenges->links() }}
        @endif
    </div>
@endsection
