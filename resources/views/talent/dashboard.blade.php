@extends('layouts.public', ['pageTitle' => 'My applications'])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="container section">
        <h1>My applications</h1>
        @include('partials.flash')
        <p>@if ($profile)<a href="{{ route('talent.profile') }}">Edit my candidate profile</a> ({{ $profile->visibility === 'startups' ? 'startups can find me' : 'hidden from search' }})@else <a class="btn btn-primary" href="{{ route('talent.profile') }}">Create my candidate profile</a>@endif
            · <a href="{{ route('jobs.index') }}">Browse jobs</a></p>

        @if ($invitations->isNotEmpty())
            <section class="panel" aria-labelledby="inv-heading">
                <h2 id="inv-heading">Invitations to apply</h2>
                @foreach ($invitations as $invitation)
                    <p><a href="{{ route('jobs.show', $invitation->vacancy) }}">{{ $invitation->vacancy->title }}</a> · {{ $invitation->vacancy->startup->display_name }}@if ($invitation->message) · “{{ $invitation->message }}”@endif</p>
                @endforeach
            </section>
        @endif

        @forelse ($applications as $application)
            <section class="panel" aria-labelledby="app-{{ $application->uuid }}">
                <h2 id="app-{{ $application->uuid }}" class="h3">{{ $application->vacancy?->title ?? 'Removed role' }} · {{ $application->startup?->display_name }}
                    <span class="badge badge-{{ $application->status === 'hired' ? 'on' : ($application->isClosed() ? 'off' : 'draft') }}">{{ \App\Models\Talent\JobApplication::CANDIDATE_LABELS[$application->status] }}</span></h2>
                <ol class="meta">
                    @foreach ($application->events as $event)
                        <li>{{ $event->created_at->format('j M Y') }}: {{ \App\Models\Talent\JobApplication::CANDIDATE_LABELS[$event->to_status] }}@if ($event->message) · “{{ $event->message }}”@endif</li>
                    @endforeach
                </ol>
                @foreach ($application->interviews->whereIn('status', ['proposed', 'confirmed', 'declined']) as $interview)
                    <div class="card">
                        <p><strong>Interview: {{ $interview->scheduled_at->format('D j M Y, H:i') }}</strong> · {{ $interview->duration_minutes }} min · {{ \App\Models\Talent\Interview::MODES[$interview->mode] }}
                            <span class="badge">{{ $interview->status === 'proposed' ? 'Please reply' : \App\Models\Talent\Interview::STATUSES[$interview->status] }}</span></p>
                        @if ($interview->location)<p>@if ($interview->mode === 'online')<a href="{{ $interview->location }}" rel="noopener nofollow" target="_blank">Join link</a>@else{{ $interview->location }}@endif</p>@endif
                        @if ($interview->status === 'proposed')
                            <form method="post" action="{{ route('talent.interviews.respond', $interview) }}">
                                @csrf
                                @include('partials.field', ['name' => 'candidate_note', 'id' => 'note-'.$interview->uuid, 'label' => 'Note to the team (optional)', 'maxlength' => 500])
                                <div class="inline-form">
                                    <button type="submit" name="decision" value="confirm" class="btn btn-primary btn-sm">Confirm</button>
                                    <button type="submit" name="decision" value="decline" class="btn btn-secondary btn-sm">Ask for another time</button>
                                </div>
                            </form>
                        @endif
                    </div>
                @endforeach
                <p class="meta">Applied {{ $application->created_at->format('j M Y') }}@if ($application->cv_name && $application->cv_path) · CV sent: {{ $application->cv_name }}@elseif ($application->cv_purged_at) · CV copy deleted {{ $application->cv_purged_at->format('j M Y') }}@endif</p>
                @unless ($application->isClosed())
                    <form method="post" action="{{ route('talent.withdraw', $application) }}" data-confirm="Withdraw this application? Your CV copy will be deleted.">
                        @csrf <button type="submit" class="btn btn-secondary btn-sm">Withdraw<span class="visually-hidden"> application for {{ $application->vacancy?->title }}</span></button>
                    </form>
                @endunless
            </section>
        @empty
            <p class="empty">You have not applied for any roles yet.</p>
        @endforelse
    </div>
@endsection
