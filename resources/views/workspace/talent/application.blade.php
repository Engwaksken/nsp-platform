@extends('layouts.workspace', ['pageTitle' => 'Application · '.$application->vacancy->title])

@push('head')<meta name="robots" content="noindex">@endpush

@section('workspace')
    <div class="container section narrow">
        <p><a href="{{ route('workspace.talent.vacancy', [$startup, $application->vacancy]) }}">← Applications for {{ $application->vacancy->title }}</a></p>
        <h1>{{ $application->applicant?->name ?? 'Deleted account' }}</h1>
        @include('partials.flash')
        <p class="meta">{{ $profile?->headline }}@if ($profile?->location) · {{ $profile->location }}@endif
            @if ($application->applicant) · <a href="mailto:{{ $application->applicant->email }}">{{ $application->applicant->email }}</a>@endif
            · applied {{ $application->created_at->format('j M Y') }}@if ($application->invited) after your invitation @endif</p>
        @if ($profile?->summary)<p>{!! nl2br(e($profile->summary)) !!}</p>@endif
        @if ($profile?->skills)<p>@foreach ($profile->skills as $skill)<span class="tag">{{ $skill }}</span>@endforeach</p>@endif
        @if ($application->cover_note)<h2 class="h3">Cover note</h2><p>{!! nl2br(e($application->cover_note)) !!}</p>@endif
        <p>@if ($application->cv_path)<a class="btn btn-secondary btn-sm" href="{{ route('workspace.talent.cv', [$startup, $application]) }}">Download CV ({{ $application->cv_name }})</a>
            @elseif ($application->cv_purged_at) <span class="meta">CV deleted on {{ $application->cv_purged_at->format('j M Y') }} (retention or withdrawal).</span>
            @else <span class="meta">No CV attached.</span>@endif</p>

        <section class="panel" aria-labelledby="stage-heading">
            <h2 id="stage-heading">Stage</h2>
            @if ($application->status === 'withdrawn')
                <p>The candidate withdrew this application.</p>
            @else
                <form method="post" action="{{ route('workspace.talent.move', [$startup, $application]) }}">
                    @csrf @method('PUT')
                    @include('partials.field', ['name' => 'status', 'label' => 'Stage', 'type' => 'select', 'required' => true, 'value' => $application->status,
                        'options' => array_diff_key(\App\Models\Talent\JobApplication::STATUSES, ['withdrawn' => true])])
                    @include('partials.field', ['name' => 'message', 'label' => 'Message to the candidate when the stage changes (optional)', 'type' => 'textarea', 'rows' => 2, 'hint' => 'The candidate sees this with the new stage.'])
                    @include('partials.field', ['name' => 'team_notes', 'label' => 'Team notes (never shown to the candidate)', 'type' => 'textarea', 'rows' => 3, 'value' => $application->team_notes])
                    <button type="submit" class="btn btn-primary">Save</button>
                </form>
            @endif
            <h3>History</h3>
            <ol class="meta">
                @foreach ($application->events as $event)
                    <li>{{ $event->created_at->format('j M Y H:i') }}: {{ \App\Models\Talent\JobApplication::STATUSES[$event->to_status] }}@if ($event->actor) by {{ $event->actor->name }}@endif @if ($event->message) · “{{ $event->message }}”@endif</li>
                @endforeach
            </ol>
        </section>

        <section class="panel" aria-labelledby="int-heading">
            <h2 id="int-heading">Interviews</h2>
            @forelse ($application->interviews as $interview)
                <div class="card">
                    <p><strong>{{ $interview->scheduled_at->format('D j M Y, H:i') }}</strong> · {{ $interview->duration_minutes }} min · {{ \App\Models\Talent\Interview::MODES[$interview->mode] }}
                        <span class="badge">{{ \App\Models\Talent\Interview::STATUSES[$interview->status] }}</span></p>
                    @if ($interview->candidate_note)<p class="meta">Candidate: “{{ $interview->candidate_note }}”</p>@endif
                    <form method="post" action="{{ route('workspace.talent.interviews.update', [$startup, $interview]) }}">
                        @csrf @method('PUT')
                        @include('partials.field', ['name' => 'status', 'id' => 'istatus-'.$interview->uuid, 'label' => 'Status', 'type' => 'select', 'value' => $interview->status,
                            'options' => [$interview->status => \App\Models\Talent\Interview::STATUSES[$interview->status], 'done' => 'Held', 'cancelled' => 'Cancelled']])
                        @include('partials.field', ['name' => 'team_notes', 'id' => 'inotes-'.$interview->uuid, 'label' => 'Interview notes (team only)', 'type' => 'textarea', 'rows' => 3, 'value' => $interview->team_notes])
                        <button type="submit" class="btn btn-secondary btn-sm">Save interview</button>
                    </form>
                </div>
            @empty
                <p class="empty">No interviews yet.</p>
            @endforelse
            @unless ($application->isClosed())
                <h3>Propose an interview</h3>
                <form method="post" action="{{ route('workspace.talent.interviews.store', [$startup, $application]) }}">
                    @csrf
                    <div class="grid">
                        @include('partials.field', ['name' => 'scheduled_at', 'label' => 'Date and time', 'type' => 'datetime-local', 'required' => true])
                        @include('partials.field', ['name' => 'duration_minutes', 'label' => 'Length (minutes)', 'type' => 'number', 'required' => true, 'value' => 45, 'attrs' => 'min="15" max="240" step="15"'])
                        @include('partials.field', ['name' => 'mode', 'label' => 'How', 'type' => 'select', 'required' => true, 'options' => \App\Models\Talent\Interview::MODES])
                    </div>
                    @include('partials.field', ['name' => 'location', 'label' => 'Meeting link or place', 'maxlength' => 255, 'hint' => 'Online interviews need an https:// link.'])
                    <button type="submit" class="btn btn-primary">Propose interview</button>
                </form>
            @endunless
        </section>
    </div>
@endsection
