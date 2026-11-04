@extends('layouts.public', ['pageTitle' => 'Opportunities', 'canonical' => route('opportunities.index')])

@section('content')
    <div class="container section">
        <div class="page-head">
            <h1>Opportunities for startups</h1>
            @auth<a class="btn btn-secondary" href="{{ route('opportunities.create') }}">Share an opportunity</a>@endauth
        </div>
        @include('partials.flash')
        <p>Grants, accelerators, competitions, events and calls from Hive and partners. Sign in to save opportunities, get deadline reminders and see whether your startup qualifies.</p>

        <form method="get" class="filters" role="search" aria-label="Filter opportunities">
            <div class="form-group"><label for="q">Search</label><input id="q" type="search" name="q" value="{{ request('q') }}"></div>
            <div class="form-group"><label for="type">Type</label>
                <select id="type" name="type"><option value="">All types</option>
                    @foreach (\App\Models\Ecosystem\Opportunity::TYPES as $value => $label)<option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>@endforeach
                </select></div>
            <div class="form-group"><label for="sector">Sector</label>
                <select id="sector" name="sector"><option value="">Any sector</option>
                    @foreach (\App\Models\Registry\StartupProfile::SECTORS as $option)<option value="{{ $option }}" @selected($sector === $option)>{{ $option }}</option>@endforeach
                </select></div>
            <div class="form-group"><label class="checkbox"><input type="checkbox" name="online" value="1" @checked(request()->boolean('online'))> <span>Online only</span></label></div>
            <div class="form-group"><label class="checkbox"><input type="checkbox" name="closing" value="soon" @checked(request('closing') === 'soon')> <span>Closing in 14 days</span></label></div>
            <button type="submit" class="btn btn-secondary">Filter</button>
        </form>

        @if ($opportunities->isEmpty())
            <p class="empty">No open opportunities match. Try fewer filters.</p>
        @else
            <div class="grid">
                @foreach ($opportunities as $opportunity)
                    <article class="card">
                        <p class="meta">{{ \App\Models\Ecosystem\Opportunity::TYPES[$opportunity->type] }}@if ($opportunity->organisation) · {{ $opportunity->organisation }}@endif</p>
                        <h2 class="h3"><a href="{{ route('opportunities.show', $opportunity) }}">{{ $opportunity->title }}</a></h2>
                        <p>{{ $opportunity->summary }}</p>
                        <p class="meta">
                            @if ($opportunity->deadline_at)Deadline {{ $opportunity->deadline_at->format('j M Y') }}@if ($opportunity->deadline_at->lte(now()->addDays(14))) <span class="badge badge-draft">Closing soon</span>@endif
                            @elseif ($opportunity->starts_at){{ $opportunity->starts_at->format('j M Y, H:i') }}@else Open @endif
                            @if ($opportunity->amount_text) · {{ $opportunity->amount_text }}@endif
                            · {{ $opportunity->is_online ? 'Online' : ($opportunity->location ?: 'Location to be confirmed') }}
                        </p>
                        @if (in_array($opportunity->id, $bookmarked, true))<span class="badge badge-on">Saved</span>@endif
                    </article>
                @endforeach
            </div>
            {{ $opportunities->links() }}
        @endif
    </div>
@endsection
