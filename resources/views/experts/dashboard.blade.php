@extends('layouts.public', ['pageTitle' => 'Expert dashboard'])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="container section">
        <h1>Expert dashboard</h1>
        @include('partials.flash')
        <p class="hint">{{ $profile->headline }} · <a href="{{ route('experts.profile') }}">Edit my profile</a></p>
        @if ($atCapacity)
            <div class="alert alert-warning" role="status">You are supporting {{ $profile->max_active }} startups, the most you set. You can still accept requests, or raise the limit in your profile.</div>
        @endif

        <section class="panel" aria-labelledby="req-heading">
            <h2 id="req-heading">New requests</h2>
            @forelse ($requests as $engagement)
                <p><a href="{{ route('engagements.show', $engagement) }}">{{ $engagement->topic }}</a> · {{ $engagement->startup?->display_name }} · {{ \App\Models\Experts\Engagement::TYPES[$engagement->type] }} · {{ $engagement->created_at->format('j M Y') }}</p>
            @empty
                <p class="empty">No new requests.</p>
            @endforelse
        </section>

        <section class="panel" aria-labelledby="up-heading">
            <h2 id="up-heading">Upcoming sessions</h2>
            @forelse ($upcoming as $session)
                <p>{{ $session->scheduled_at->format('D j M Y, H:i') }} · <a href="{{ route('engagements.show', $session->engagement_uuid) }}">{{ $session->engagement?->startup?->display_name }}</a> · {{ \App\Models\Experts\ExpertSession::MODES[$session->mode] }}</p>
            @empty
                <p class="empty">No sessions planned.</p>
            @endforelse
        </section>

        <section aria-labelledby="active-heading">
            <h2 id="active-heading">Startups you support</h2>
            <div class="table-wrap" tabindex="0" role="region" aria-label="Startups you support">
                <table>
                    <thead><tr><th scope="col">Startup</th><th scope="col">Topic</th><th scope="col">Type</th><th scope="col">Sessions held</th></tr></thead>
                    <tbody>
                    @forelse ($active as $engagement)
                        <tr>
                            <td>{{ $engagement->startup?->display_name }}<br><span class="meta">{{ $engagement->startup?->profile?->sector }}</span></td>
                            <td><a href="{{ route('engagements.show', $engagement) }}">{{ $engagement->topic }}</a></td>
                            <td>{{ \App\Models\Experts\Engagement::TYPES[$engagement->type] }}@if ($engagement->source === 'staff_assignment') (assigned by Hive)@endif</td>
                            <td>{{ $engagement->sessions_count }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty">None yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if ($past->isNotEmpty())
            <h2>Earlier</h2>
            <ul>
                @foreach ($past as $engagement)
                    <li><a href="{{ route('engagements.show', $engagement) }}">{{ $engagement->topic }}</a> · {{ $engagement->startup?->display_name }} · {{ \App\Models\Experts\Engagement::STATUSES[$engagement->status] }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
