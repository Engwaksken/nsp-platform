@extends('layouts.public', ['pageTitle' => 'Experts · '.$startup->display_name])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="container section">
        <h1>Experts and mentors · {{ $startup->display_name }}</h1>
        @include('workspace.startups.nav')
        @include('partials.flash')
        <p class="hint">Experts here are checked by Hive. When you send a request, the expert sees your startup’s name, sector and what you write. Contact details are shared once they accept.</p>

        @if ($engagements->isNotEmpty())
            <section class="panel" aria-labelledby="mine-heading">
                <h2 id="mine-heading">Your requests and mentors</h2>
                <ul>
                    @foreach ($engagements as $engagement)
                        <li><a href="{{ route('engagements.show', $engagement) }}">{{ $engagement->topic }}</a>
                            · {{ \App\Models\Experts\Engagement::TYPES[$engagement->type] }} with {{ $engagement->expert?->name ?? 'former expert' }}
                            <span class="badge badge-{{ $engagement->status === 'active' ? 'on' : ($engagement->status === 'requested' ? 'draft' : 'off') }}">{{ \App\Models\Experts\Engagement::STATUSES[$engagement->status] }}</span></li>
                    @endforeach
                </ul>
            </section>
        @endif

        <form method="get" class="filters" role="search" aria-label="Find experts">
            <div class="form-group"><label for="q">Name or keyword</label><input id="q" type="search" name="q" value="{{ $q }}"></div>
            <div class="form-group"><label for="kind">Looking for</label>
                <select id="kind" name="kind">
                    <option value="">Anyone</option>
                    <option value="mentor" @selected($kind === 'mentor')>A mentor</option>
                    <option value="consultant" @selected($kind === 'consultant')>A consultant</option>
                </select>
            </div>
            <details class="form-group">
                <summary>Expertise @if ($areas)({{ count($areas) }} chosen)@endif</summary>
                @foreach (\App\Models\Experts\ExpertProfile::areas() as $value => $label)
                    <label class="checkbox"><input type="checkbox" name="areas[]" value="{{ $value }}" @checked(in_array($value, $areas, true))> {{ $label }}</label>
                @endforeach
            </details>
            <button class="btn btn-secondary" type="submit">Search</button>
        </form>

        <div class="grid">
            @forelse ($rows as $row)
                @php($expert = $row['expert'])
                <article class="card" aria-labelledby="exp-{{ $expert->id }}">
                    <h2 id="exp-{{ $expert->id }}" class="h4">{{ $expert->user->name }}</h2>
                    <p><strong>{{ $expert->headline }}</strong></p>
                    <p class="meta">{{ \App\Models\Experts\ExpertProfile::KINDS[$expert->kind] }}
                        @if ($expert->languages) · {{ collect($expert->languages)->map(fn ($l) => \App\Models\Experts\ExpertProfile::LANGUAGES[$l] ?? $l)->join(', ') }}@endif
                        @isset($ratings[$expert->user_id]) · Rated {{ $ratings[$expert->user_id]['average'] }}/5 by {{ $ratings[$expert->user_id]['count'] }} startups @endisset</p>
                    <p>{{ \Illuminate\Support\Str::limit($expert->bio, 280) }}</p>
                    <p>@foreach ($expert->areaLabels() as $label)<span class="tag">{{ $label }}</span>@endforeach</p>
                    @if ($row['reasons'])<p class="meta">Why: {{ implode(' · ', $row['reasons']) }}</p>@endif
                    <p class="meta">@if ($expert->offers_free) Free sessions available. @endif @if ($expert->rate_note) {{ $expert->rate_note }} @endif
                        @if ($expert->availability) Availability: {{ $expert->availability }} @endif</p>
                    @if ($membership->canEdit() && ! in_array($expert->user_id, $openWith, true))
                        <details>
                            <summary>Ask {{ $expert->user->name }}</summary>
                            <form method="post" action="{{ route('workspace.experts.request', $startup) }}">
                                @csrf
                                <input type="hidden" name="expert_user_id" value="{{ $expert->user_id }}">
                                @include('partials.field', ['name' => 'type', 'id' => 'type-'.$expert->id, 'label' => 'What you need', 'type' => 'select', 'required' => true,
                                    'options' => $expert->offersMentoring() ? \App\Models\Experts\Engagement::TYPES : ['consultation' => 'Consultation']])
                                @include('partials.field', ['name' => 'topic', 'id' => 'topic-'.$expert->id, 'label' => 'Topic', 'required' => true, 'maxlength' => 150])
                                @include('partials.field', ['name' => 'details', 'id' => 'details-'.$expert->id, 'label' => 'What would help you most?', 'type' => 'textarea', 'rows' => 3])
                                @include('partials.field', ['name' => 'preferred_times', 'id' => 'times-'.$expert->id, 'label' => 'Times that suit you', 'maxlength' => 255])
                                <button type="submit" class="btn btn-primary btn-sm">Send request</button>
                            </form>
                        </details>
                    @elseif (in_array($expert->user_id, $openWith, true))
                        <p class="meta">You already have an open request or engagement with this expert.</p>
                    @endif
                </article>
            @empty
                <p class="empty">No verified experts match. Try fewer filters.</p>
            @endforelse
        </div>
    </div>
@endsection
