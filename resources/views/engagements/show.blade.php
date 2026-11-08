@extends('layouts.public', ['pageTitle' => $engagement->topic])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    @php($startup = $engagement->startup)
    @php($open = $engagement->isOpen())
    <div class="container section narrow">
        <p>@if ($isExpert)<a href="{{ route('experts.dashboard') }}">← Expert dashboard</a>@else<a href="{{ route('workspace.experts', $startup) }}">← Experts for {{ $startup->display_name }}</a>@endif</p>
        <h1>{{ $engagement->topic }}</h1>
        @include('partials.flash')
        <p class="meta">{{ \App\Models\Experts\Engagement::TYPES[$engagement->type] }}
            · {{ $startup->display_name }}@if ($startup->profile?->sector) ({{ $startup->profile->sector }})@endif
            with {{ $engagement->expert?->name ?? 'former expert' }}
            · @if ($engagement->source === 'staff_assignment') assigned by Hive @else requested by {{ $engagement->requester?->name ?? 'the startup' }} @endif on {{ $engagement->created_at->format('j M Y') }}
            <span class="badge badge-{{ $engagement->status === 'active' ? 'on' : ($engagement->status === 'requested' ? 'draft' : 'off') }}">{{ \App\Models\Experts\Engagement::STATUSES[$engagement->status] }}</span></p>
        @if ($engagement->details)<p>{!! nl2br(e($engagement->details)) !!}</p>@endif
        @if ($engagement->preferred_times)<p class="meta">Times that suit the startup: {{ $engagement->preferred_times }}</p>@endif
        @if ($engagement->response_note)<p class="meta">Note from the expert: {{ $engagement->response_note }}</p>@endif

        @if ($engagement->status === 'requested' && $isExpert)
            <section class="panel" aria-labelledby="answer-heading">
                <h2 id="answer-heading">Your answer</h2>
                <form method="post" action="{{ route('engagements.decide', $engagement) }}">
                    @csrf
                    @include('partials.field', ['name' => 'response_note', 'label' => 'Note to the founders (optional)', 'type' => 'textarea', 'rows' => 2])
                    <div class="inline-form">
                        <button type="submit" name="decision" value="accept" class="btn btn-primary">Accept</button>
                        <button type="submit" name="decision" value="decline" class="btn btn-secondary">Decline</button>
                    </div>
                </form>
            </section>
        @endif

        @if ($connected)
            <section class="panel" aria-labelledby="contact-heading">
                <h2 id="contact-heading">Contacts</h2>
                <ul>
                    @if ($isExpert)
                        @foreach ($contacts as $member)
                            <li>{{ $member->user?->name }} ({{ \App\Models\Registry\StartupMembership::ROLES[$member->role] ?? $member->role }}) · <a href="mailto:{{ $member->user?->email }}">{{ $member->user?->email }}</a></li>
                        @endforeach
                    @else
                        <li>{{ $engagement->expert?->name }} · <a href="mailto:{{ $engagement->expert?->email }}">{{ $engagement->expert?->email }}</a>@if ($expertProfile?->website) · <a href="{{ $expertProfile->website }}" rel="noopener nofollow" target="_blank">{{ $expertProfile->website }}</a>@endif</li>
                    @endif
                </ul>
            </section>
        @endif

        @if (in_array($engagement->status, ['active', 'completed'], true))
            <section class="panel" aria-labelledby="sessions-heading">
                <h2 id="sessions-heading">Sessions</h2>
                @forelse ($engagement->sessions as $session)
                    <div class="card">
                        <h3 class="h4">{{ $session->scheduled_at->format('D j M Y, H:i') }} · {{ $session->duration_minutes }} min · {{ \App\Models\Experts\ExpertSession::MODES[$session->mode] }}
                            <span class="badge">{{ \App\Models\Experts\ExpertSession::STATUSES[$session->status] }}</span></h3>
                        @if ($session->location)
                            <p>@if ($session->mode === 'online')<a href="{{ $session->location }}" rel="noopener nofollow" target="_blank">Join link</a>@else{{ $session->location }}@endif</p>
                        @endif
                        @if ($canEdit)
                        <form method="post" action="{{ route('engagements.sessions.update', [$engagement, $session]) }}">
                            @csrf @method('PUT')
                            @include('partials.field', ['name' => 'shared_notes', 'id' => 'shared-'.$session->uuid, 'label' => 'Shared notes (the startup team and the expert see these)', 'type' => 'textarea', 'rows' => 3, 'value' => $session->shared_notes])
                            @include('partials.field', ['name' => 'private_notes', 'id' => 'private-'.$session->uuid, 'label' => $isExpert ? 'My private notes (only I see these)' : 'Team notes (only your startup team sees these)', 'type' => 'textarea', 'rows' => 2, 'value' => $isExpert ? $session->expert_notes : $session->startup_notes])
                            @include('partials.field', ['name' => 'status', 'id' => 'status-'.$session->uuid, 'label' => 'Status', 'type' => 'select', 'value' => $session->status, 'options' => \App\Models\Experts\ExpertSession::STATUSES])
                            <button type="submit" class="btn btn-secondary btn-sm">Save session</button>
                        </form>
                        @else
                            @if ($session->shared_notes)<p><strong>Shared notes:</strong> {!! nl2br(e($session->shared_notes)) !!}</p>@endif
                            @if ($session->startup_notes)<p><strong>Team notes:</strong> {!! nl2br(e($session->startup_notes)) !!}</p>@endif
                        @endif
                    </div>
                @empty
                    <p class="empty">No sessions yet.</p>
                @endforelse

                @if ($engagement->status === 'active' && $canEdit)
                    <h3>Plan a session</h3>
                    <form method="post" action="{{ route('engagements.sessions.store', $engagement) }}">
                        @csrf
                        <div class="grid">
                            @include('partials.field', ['name' => 'scheduled_at', 'label' => 'Date and time', 'type' => 'datetime-local', 'required' => true])
                            @include('partials.field', ['name' => 'duration_minutes', 'label' => 'Length (minutes)', 'type' => 'number', 'required' => true, 'value' => 60, 'attrs' => 'min="15" max="480" step="15"'])
                            @include('partials.field', ['name' => 'mode', 'label' => 'How', 'type' => 'select', 'required' => true, 'options' => \App\Models\Experts\ExpertSession::MODES])
                        </div>
                        @include('partials.field', ['name' => 'location', 'label' => 'Meeting link or place', 'maxlength' => 255, 'hint' => 'Online sessions need an https:// link.'])
                        <button type="submit" class="btn btn-primary">Plan session</button>
                    </form>
                @endif
            </section>

            <section class="panel" aria-labelledby="actions-heading">
                <h2 id="actions-heading">Actions</h2>
                <ul class="plain-list">
                    @forelse ($engagement->actions as $action)
                        <li>
                            <form method="post" action="{{ route('engagements.actions.toggle', [$engagement, $action]) }}" class="inline-form">
                                @csrf
                                @if ($canEdit)<button type="submit" class="btn btn-secondary btn-sm" aria-pressed="{{ $action->done_at ? 'true' : 'false' }}">{{ $action->done_at ? 'Done' : 'Mark done' }}<span class="visually-hidden">: {{ $action->title }}</span></button>@elseif ($action->done_at)<span class="badge badge-on">Done</span>@endif
                                <span @if ($action->done_at) class="meta" @endif>{{ $action->title }}</span>
                                <span class="meta">{{ \App\Models\Experts\SessionAction::OWNERS[$action->owner] }}@if ($action->due_on) · due {{ $action->due_on->format('j M Y') }}@endif</span>
                                @if ($action->isOverdue())<span class="badge badge-off">Overdue</span>@endif
                            </form>
                        </li>
                    @empty
                        <li class="empty">No actions yet.</li>
                    @endforelse
                </ul>
                @if ($open && $canEdit)
                    <form method="post" action="{{ route('engagements.actions.store', $engagement) }}">
                        @csrf
                        <div class="grid">
                            @include('partials.field', ['name' => 'title', 'label' => 'New action', 'required' => true, 'maxlength' => 200])
                            @include('partials.field', ['name' => 'owner', 'label' => 'Who', 'type' => 'select', 'required' => true, 'options' => \App\Models\Experts\SessionAction::OWNERS])
                            @include('partials.field', ['name' => 'due_on', 'label' => 'Due', 'type' => 'date'])
                        </div>
                        <button type="submit" class="btn btn-secondary">Add action</button>
                    </form>
                @endif
            </section>
        @endif

        @if ($engagement->status === 'completed')
            <section class="panel" aria-labelledby="outcome-heading">
                <h2 id="outcome-heading">Outcome</h2>
                <p>{{ $engagement->outcome ?: 'No outcome written.' }}</p>
                @if ($engagement->review)
                    <p>Feedback: {{ $engagement->review->rating }}/5 @if ($engagement->review->helped !== null)· {{ $engagement->review->helped ? 'It helped' : 'It did not help' }}@endif</p>
                    @if ($isExpert && $engagement->review->comment)<p>“{{ $engagement->review->comment }}”</p>@endif
                @elseif (! $isExpert && $canEdit)
                    <form method="post" action="{{ route('engagements.review', $engagement) }}">
                        @csrf
                        <h3>How did it go?</h3>
                        @include('partials.field', ['name' => 'rating', 'label' => 'Rating', 'type' => 'select', 'required' => true, 'placeholder' => 'Choose…', 'options' => [5 => '5 · Excellent', 4 => '4 · Good', 3 => '3 · OK', 2 => '2 · Poor', 1 => '1 · Very poor']])
                        @include('partials.field', ['name' => 'helped', 'label' => 'Did this help your startup?', 'type' => 'select', 'placeholder' => 'Prefer not to say', 'options' => [1 => 'Yes', 0 => 'No']])
                        @include('partials.field', ['name' => 'comment', 'label' => 'Comment (the expert and Hive staff see this; other founders do not)', 'type' => 'textarea', 'rows' => 3])
                        <button type="submit" class="btn btn-primary">Send feedback</button>
                    </form>
                @endif
            </section>
        @endif

        @if ($open && $canEdit)
            <section class="panel" aria-labelledby="close-heading">
                <h2 id="close-heading">Finish</h2>
                @if ($engagement->status === 'active')
                    <form method="post" action="{{ route('engagements.complete', $engagement) }}">
                        @csrf
                        @include('partials.field', ['name' => 'outcome', 'label' => 'What changed? (optional)', 'type' => 'textarea', 'rows' => 2])
                        <button type="submit" class="btn btn-primary">Mark as completed</button>
                    </form>
                @endif
                <form method="post" action="{{ route('engagements.cancel', $engagement) }}" data-confirm="Cancel this {{ strtolower(\App\Models\Experts\Engagement::TYPES[$engagement->type]) }}?" class="inline-form">
                    @csrf <button type="submit" class="btn btn-secondary">Cancel {{ strtolower(\App\Models\Experts\Engagement::TYPES[$engagement->type]) }}</button>
                </form>
            </section>
        @endif
    </div>
@endsection
