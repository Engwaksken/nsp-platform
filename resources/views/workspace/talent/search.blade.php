@extends('layouts.public', ['pageTitle' => 'Find candidates · '.$startup->display_name])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="container section">
        <h1>Find candidates · {{ $startup->display_name }}</h1>
        @include('workspace.startups.nav')
        @include('partials.flash')
        <p class="hint">Only people who chose to be found by startups appear here. You see their headline, skills, location and preferences, not their email or CV. Invite them to apply to an open vacancy; it is their choice to apply.</p>

        <form method="get" class="filters" role="search" aria-label="Find candidates">
            <div class="form-group"><label for="q">Skill, headline or place</label><input id="q" type="search" name="q" value="{{ $q }}" placeholder="e.g. designer, Kampala" maxlength="100"></div>
            <div class="form-group"><label for="type">Looking for</label>
                <select id="type" name="type"><option value="">Any</option>
                    @foreach (\App\Models\Talent\Vacancy::TYPES as $value => $label)<option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>@endforeach
                </select></div>
            <button class="btn btn-secondary" type="submit">Search</button>
        </form>

        @if (! $openVacancies)
            <div class="alert alert-warning" role="status">Publish a vacancy to invite candidates. <a href="{{ route('workspace.talent', $startup) }}">Your vacancies</a></div>
        @endif

        <div class="grid">
            @forelse ($candidates as $candidate)
                <article class="card" aria-labelledby="cand-{{ $candidate->id }}">
                    <h2 id="cand-{{ $candidate->id }}" class="h4">{{ $candidate->user->name }}</h2>
                    <p><strong>{{ $candidate->headline }}</strong></p>
                    <p class="meta">{{ $candidate->location }}@if ($candidate->country) · {{ \App\Models\Registry\Startup::COUNTRIES[$candidate->country] ?? $candidate->country }}@endif
                        @if ($candidate->work_types) · {{ collect($candidate->work_types)->map(fn ($t) => \App\Models\Talent\Vacancy::TYPES[$t] ?? $t)->join(', ') }}@endif</p>
                    <p>@foreach ($candidate->skills ?? [] as $skill)<span class="tag">{{ $skill }}</span>@endforeach</p>
                    @php($already = ($invited[$candidate->user_id] ?? collect())->pluck('vacancy_uuid')->all())
                    @php($choices = array_diff_key($openVacancies, array_flip($already)))
                    @if ($choices)
                        <form method="post" action="{{ route('workspace.talent.invite', $startup) }}">
                            @csrf
                            <input type="hidden" name="user_id" value="{{ $candidate->user_id }}">
                            @include('partials.field', ['name' => 'vacancy_uuid', 'id' => 'vac-'.$candidate->id, 'label' => 'Vacancy', 'type' => 'select', 'required' => true, 'options' => $choices])
                            @include('partials.field', ['name' => 'message', 'id' => 'msg-'.$candidate->id, 'label' => 'Short message (optional)', 'maxlength' => 1000])
                            <button type="submit" class="btn btn-primary btn-sm">Invite to apply<span class="visually-hidden"> {{ $candidate->user->name }}</span></button>
                        </form>
                    @elseif ($already)
                        <p class="meta">Invited.</p>
                    @endif
                </article>
            @empty
                <p class="empty">No candidates match.</p>
            @endforelse
        </div>
        {{ $candidates->links() }}
    </div>
@endsection
