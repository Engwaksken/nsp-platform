@extends('layouts.app', ['pageTitle' => $startup->display_name.' · Investor view'])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="container section narrow">
        <p><a href="{{ route('investor.dashboard') }}"><i class="fas fa-arrow-left" aria-hidden="true"></i> Investor dashboard</a></p>
        <h1>{{ $startup->display_name }}</h1>
        @include('partials.flash')
        <p class="meta">{{ $profile?->sector ?: 'Sector not given' }}
            @if ($profile?->headquarters_location) · {{ $profile->headquarters_location }}@endif
            · {{ \App\Models\Registry\Startup::COUNTRIES[$startup->country] ?? $startup->country }}
            @if ($stage) · Growth stage: {{ $stage }}@endif</p>

        @if ($request)
            <section class="panel" aria-labelledby="request-heading">
                <h2 id="request-heading">Raising {{ $request->amountLabel() }}</h2>
                <p class="meta">{{ collect($request->instruments)->map(fn ($i) => \App\Models\Funding\InvestorProfile::INSTRUMENTS[$i] ?? $i)->join(', ') }}</p>
                <p>{!! nl2br(e($request->pitch)) !!}</p>
                @if ($request->use_of_funds)<h3>Use of funds</h3><p>{!! nl2br(e($request->use_of_funds)) !!}</p>@endif
                @if ($fit)
                    <p>@foreach ($fit['reasons'] as $reason)<span class="tag">{{ $reason }}</span>@endforeach
                        @if ($fit['misses'])<span class="meta">Outside your focus: {{ implode(', ', $fit['misses']) }}</span>@endif</p>
                @endif
            </section>
        @else
            <p class="alert alert-warning" role="status">This startup is not raising on NSP right now.</p>
        @endif

        <section class="panel" aria-labelledby="connect-heading">
            <h2 id="connect-heading">Connection</h2>
            @if ($connected)
                <p><span class="badge badge-on">Connected</span></p>
                <ul>
                    @foreach ($contacts as $member)
                        <li>{{ $member->user?->name }} ({{ \App\Models\Registry\StartupMembership::ROLES[$member->role] ?? $member->role }}) · <a href="mailto:{{ $member->user?->email }}">{{ $member->user?->email }}</a></li>
                    @endforeach
                </ul>
            @elseif ($introduction?->status === 'requested')
                <p>{{ $introduction->requested_by === 'investor' ? 'Waiting for the founders to reply.' : 'The founders asked to connect. Reply on your dashboard.' }}</p>
            @elseif ($request)
                @if ($introduction?->status === 'declined')<p class="meta">An earlier introduction was declined. You can ask again.</p>@endif
                <form method="post" action="{{ route('investor.interest', $startup) }}">
                    @csrf
                    @include('partials.field', ['name' => 'message', 'label' => 'Message to the founders', 'type' => 'textarea', 'rows' => 3, 'required' => true, 'hint' => 'Say who you are and what interests you. Contact details are shared only if they accept.'])
                    <button type="submit" class="btn btn-primary">Express interest</button>
                </form>
            @endif

            @if ($introduction && in_array($introduction->status, ['requested', 'accepted'], true))
                <h3>My private notes</h3>
                <form method="post" action="{{ route('investor.pipeline', $introduction) }}">
                    @csrf @method('PUT')
                    @include('partials.field', ['name' => 'investor_stage', 'label' => 'Stage', 'type' => 'select', 'value' => $introduction->investor_stage, 'placeholder' => 'Not set', 'options' => \App\Models\Funding\Introduction::STAGES])
                    @include('partials.field', ['name' => 'investor_notes', 'label' => 'Notes (only you can see these)', 'type' => 'textarea', 'rows' => 4, 'value' => $introduction->investor_notes])
                    <button type="submit" class="btn btn-secondary">Save notes</button>
                </form>
            @endif
        </section>

        @if (\App\Support\Features::enabled('capital_exchange') && Route::has('investor.capital.pipeline'))
            <section class="panel" aria-labelledby="capital-heading">
                <h2 id="capital-heading">Capital Exchange</h2>
                <p class="hint">Your pipeline stage, watchlist and match feedback are private. The startup does not see them.</p>
                <div class="capital-actions">
                    @if ($watched)
                        <form method="post" action="{{ route('investor.capital.unwatch', $startup) }}" class="inline-form">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-eye-slash" aria-hidden="true"></i> Stop watching</button>
                        </form>
                    @else
                        <form method="post" action="{{ route('investor.capital.watch', $startup) }}" class="inline-form">
                            @csrf <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-eye" aria-hidden="true"></i> Watch</button>
                        </form>
                    @endif
                    @if ($connected)
                        <a class="btn btn-secondary btn-sm" href="{{ route('investor.capital.diligence', $startup) }}"><i class="fas fa-list-check" aria-hidden="true"></i> Due diligence</a>
                    @endif
                    @if ($connected && $scopes)
                        <a class="btn btn-secondary btn-sm" href="{{ route('investor.capital.readiness', $startup) }}"><i class="fas fa-clipboard-check" aria-hidden="true"></i> Readiness report</a>
                    @endif
                    <a class="btn btn-secondary btn-sm" href="{{ route('investor.capital.pipeline') }}"><i class="fas fa-table-columns" aria-hidden="true"></i> My pipeline</a>
                </div>
                @if ($connected && ! $scopes)
                    <p class="meta">The founders have not shared readiness information with you.</p>
                @endif

                <form method="post" action="{{ route('investor.capital.transition', $startup) }}" class="inline-form">
                    @csrf
                    @include('partials.field', ['name' => 'stage', 'id' => 'capital-stage', 'label' => 'Pipeline stage', 'type' => 'select', 'value' => $pipelineItem?->stage,
                        'placeholder' => $pipelineItem ? false : 'Not in my pipeline', 'options' => \App\Models\Capital\PipelineItem::stages()])
                    <button type="submit" class="btn btn-primary btn-sm">{{ $pipelineItem ? 'Move' : 'Add to pipeline' }}</button>
                </form>

                <h3>Is this a useful match?</h3>
                <form method="post" action="{{ route('investor.capital.feedback', $startup) }}" class="capital-feedback">
                    @csrf
                    <label class="visually-hidden" for="capital-feedback-reason">Reason (optional)</label>
                    <input id="capital-feedback-reason" type="text" name="reason" maxlength="500" placeholder="Reason (optional)" value="{{ $matchFeedback?->reason }}">
                    <button type="submit" name="rating" value="useful" class="btn btn-secondary btn-sm"><i class="fas fa-thumbs-up" aria-hidden="true"></i> Useful</button>
                    <button type="submit" name="rating" value="not_useful" class="btn btn-secondary btn-sm"><i class="fas fa-thumbs-down" aria-hidden="true"></i> Not useful</button>
                </form>
                @if ($matchFeedback)<p class="meta">Your answer: {{ \App\Models\Capital\MatchFeedback::RATINGS[$matchFeedback->rating] ?? $matchFeedback->rating }}.</p>@endif
            </section>
        @endif

        <section class="panel" aria-labelledby="room-heading">
            <h2 id="room-heading">Data room</h2>
            @if ($grant)
                <p class="meta">Shared with you until {{ $grant->expires_at->format('j M Y') }}. Downloads are logged and visible to the startup.</p>
                <ul>
                    @forelse ($files as $file)
                        <li><a href="{{ route('dataroom.download', $file) }}">{{ $file->title }}</a> <span class="meta">{{ \App\Models\Funding\DataRoomFile::CATEGORIES[$file->category] ?? $file->category }}</span></li>
                    @empty
                        <li class="empty">No documents yet.</li>
                    @endforelse
                </ul>
            @else
                <p class="empty">The startup has not shared its documents with you.</p>
            @endif
        </section>
    </div>
@endsection
