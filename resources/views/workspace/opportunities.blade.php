@extends('layouts.workspace', ['pageTitle' => 'Opportunities · '.$startup->display_name])

@push('head')<meta name="robots" content="noindex">@endpush

@section('workspace')
    <div class="container section">
        <h1>Opportunities · {{ $startup->display_name }}</h1>
        @include('partials.flash')

        @php($closingSoon = collect($recommended ?: [])->filter(fn ($item) => $item['opportunity']->deadline_at && $item['opportunity']->deadline_at->between(now(), now()->addDays(14)))->count())
        @include('partials.stats', ['label' => 'Opportunities at a glance', 'stats' => [
            ['icon' => 'fa-bullseye', 'tone' => 'orange', 'label' => 'Recommended', 'value' => count($recommended ?: [])],
            ['icon' => 'fa-hourglass-half', 'tone' => 'red', 'label' => 'Closing in 14 days', 'value' => $closingSoon],
            ['icon' => 'fa-bookmark', 'tone' => 'blue', 'label' => 'Saved', 'value' => $saved->count()],
            ['icon' => 'fa-clipboard-check', 'tone' => 'green', 'label' => 'Registrations', 'value' => $registrations->count()],
        ]])

        <div data-tabs="Opportunity sections" data-tabs-key="opportunities">
        <section aria-labelledby="rec-heading" data-tab="Recommended" data-tab-icon="fa-bullseye" data-tab-count="{{ count($recommended ?: []) }}">
            <h2 id="rec-heading">Recommended for {{ $startup->display_name }}</h2>
            <p class="hint">Matched on your profile (sector, country, growth stage, registration, founding year), nearest deadlines first. Each card says why it is shown.
                <a href="{{ route('opportunities.index') }}">Browse all opportunities</a>.</p>
            @if (!$recommended)
                <p class="empty">No open opportunities match right now. Complete your profile and growth assessment for better matches.</p>
            @else
                <div class="grid">
                    @foreach ($recommended as $item)
                        @php($opportunity = $item['opportunity'])
                        <article class="card">
                            <p class="meta">{{ \App\Models\Ecosystem\Opportunity::TYPES[$opportunity->type] }}@if ($opportunity->deadline_at) · closes {{ $opportunity->deadline_at->format('j M') }}@endif</p>
                            <h3><a href="{{ route('opportunities.show', $opportunity) }}">{{ $opportunity->title }}</a></h3>
                            <p class="meta">Why: {{ implode(' · ', $item['reasons']) }}@if ($item['unknown']) · Check: {{ implode(', ', $item['unknown']) }} (missing from your profile)@endif</p>
                            <form method="post" action="{{ route('workspace.opportunities.feedback', [$startup, $opportunity]) }}">
                                @csrf
                                <input type="hidden" name="feedback" value="not_relevant">
                                <button type="submit" class="btn-link">Not relevant<span class="visually-hidden">: {{ $opportunity->title }}</span></button>
                            </form>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="panel" aria-labelledby="saved-heading" data-tab="Saved" data-tab-icon="fa-bookmark" data-tab-count="{{ $saved->count() }}">
            <h2 id="saved-heading">Saved by you</h2>
            @forelse ($saved as $bookmark)
                <p><a href="{{ route('opportunities.show', $bookmark->opportunity) }}">{{ $bookmark->opportunity->title }}</a>
                    <span class="meta">@if ($bookmark->opportunity->deadline_at) · deadline {{ $bookmark->opportunity->deadline_at->format('j M Y') }}@endif</span></p>
            @empty
                <p class="empty">Nothing saved yet.</p>
            @endforelse
        </section>

        <section class="panel" aria-labelledby="reg-heading" data-tab="Registrations" data-tab-icon="fa-clipboard-check" data-tab-count="{{ $registrations->count() }}">
            <h2 id="reg-heading">Registrations and interest</h2>
            @forelse ($registrations as $registration)
                <p><a href="{{ route('opportunities.show', $registration->opportunity) }}">{{ $registration->opportunity->title }}</a>
                    · {{ \App\Models\Ecosystem\OpportunityInterest::STATUSES[$registration->status] }} <span class="meta">({{ $registration->user?->name }})</span></p>
            @empty
                <p class="empty">None yet.</p>
            @endforelse
        </section>

        @if ($mySubmissions->isNotEmpty())
            <section class="panel" aria-labelledby="sub-heading" data-tab="Shared by you" data-tab-icon="fa-share-nodes" data-tab-count="{{ $mySubmissions->count() }}">
                <h2 id="sub-heading">Opportunities you shared</h2>
                @foreach ($mySubmissions as $submission)
                    <p>{{ $submission->title }} · <span class="badge badge-{{ ['published' => 'on', 'pending_review' => 'draft'][$submission->status] ?? 'off' }}">{{ ['pending_review' => 'Waiting for review', 'published' => 'Published', 'rejected' => 'Not published', 'draft' => 'Draft', 'archived' => 'Archived'][$submission->status] }}</span>
                        @if ($submission->status === 'rejected' && $submission->moderation_note)<br><span class="meta">Reason: {{ $submission->moderation_note }}</span>@endif</p>
                @endforeach
            </section>
        @endif
        </div>
    </div>
@endsection
