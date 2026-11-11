@extends('layouts.browse', ['pageTitle' => $opportunity->title, 'metaDescription' => $opportunity->summary, 'canonical' => route('opportunities.show', $opportunity)])

@section('content')
    <article class="container section narrow">
        <p class="meta"><a href="{{ route('opportunities.index') }}">Opportunities</a> · {{ \App\Models\Ecosystem\Opportunity::TYPES[$opportunity->type] }}</p>
        <h1>{{ $opportunity->title }}</h1>
        @include('partials.flash')
        @if ($opportunity->status !== 'published')<div class="alert alert-warning">Not published ({{ $opportunity->status }}). Only Hive staff can see this page.</div>@endif
        @if (!$opportunity->isOpen() && $opportunity->status === 'published')<div class="alert alert-info">This opportunity is closed.</div>@endif

        <dl>
            @if ($opportunity->organisation)<dt>Organiser</dt><dd>{{ $opportunity->organisation }}</dd>@endif
            @if ($opportunity->deadline_at)<dt>Deadline</dt><dd><time datetime="{{ $opportunity->deadline_at->toIso8601String() }}">{{ $opportunity->deadline_at->format('l j F Y, H:i') }}</time></dd>@endif
            @if ($opportunity->starts_at)<dt>When</dt><dd>{{ $opportunity->starts_at->format('l j F Y, H:i') }}@if ($opportunity->ends_at) – {{ $opportunity->ends_at->format($opportunity->ends_at->isSameDay($opportunity->starts_at) ? 'H:i' : 'j F Y, H:i') }}@endif</dd>@endif
            <dt>Where</dt><dd>{{ $opportunity->is_online ? 'Online' : ($opportunity->location ?: 'To be confirmed') }}</dd>
            @if ($opportunity->amount_text)<dt>Value</dt><dd>{{ $opportunity->amount_text }}</dd>@endif
        </dl>
        <p class="lead">{{ $opportunity->summary }}</p>
        <div class="prose">{!! $bodyHtml !!}</div>

        @if ($checks !== null && $checks !== [])
            <section class="panel" aria-labelledby="fit-heading">
                <h2 id="fit-heading">Does {{ $activeStartup->display_name }} qualify?</h2>
                <ul>
                    @foreach ($checks as $check)
                        <li><strong>@if ($check['met'] === true)<i class="fas fa-check" aria-hidden="true"></i> Yes @elseif ($check['met'] === false)<i class="fas fa-xmark" aria-hidden="true"></i> No @else<i class="fas fa-circle-question" aria-hidden="true"></i> Unknown @endif</strong> · {{ $check['rule'] }}: {{ $check['detail'] }}</li>
                    @endforeach
                </ul>
                <p class="hint">Guidance from your profile only. The organiser decides who is eligible.</p>
            </section>
        @elseif ($checks === [])
            <p class="hint">Open to all startups.</p>
        @endif

        <div class="actions" style="margin-top:1rem">
            @if ($opportunity->apply_url && $opportunity->isOpen())
                <a class="btn btn-primary" href="{{ $opportunity->apply_url }}" rel="noopener" target="_blank">Apply on the organiser’s site</a>
            @endif
            @auth
                @if ($opportunity->status === 'published')
                    <form method="post" action="{{ route('opportunities.bookmark', $opportunity) }}">
                        @csrf
                        <button type="submit" class="btn btn-secondary">{{ $bookmarked ? 'Unsave' : 'Save and remind me' }}</button>
                    </form>
                @endif
            @else
                <a class="btn btn-secondary" href="{{ route('login') }}">Sign in to save or register</a>
            @endauth
        </div>

        @auth
            @if ($opportunity->accepts_interest && $opportunity->isOpen())
                <section class="panel" aria-labelledby="register-heading" style="margin-top:1.5rem">
                    <h2 id="register-heading">{{ $opportunity->type === 'event' ? 'Register' : 'Express interest' }}</h2>
                    @error('register')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
                    @if ($interest && $interest->status !== 'withdrawn')
                        <p>You are {{ strtolower(\App\Models\Ecosystem\OpportunityInterest::STATUSES[$interest->status]) }}.</p>
                        <form method="post" action="{{ route('opportunities.withdraw', $opportunity) }}" data-confirm="Withdraw your registration?">
                            @csrf
                            <button type="submit" class="btn btn-secondary">Withdraw</button>
                        </form>
                    @else
                        @php($left = $opportunity->spacesLeft())
                        @if ($left !== null)<p class="meta">{{ $left }} place{{ $left === 1 ? '' : 's' }} left.</p>@endif
                        <form method="post" action="{{ route('opportunities.register', $opportunity) }}">
                            @csrf
                            @if ($memberships->isNotEmpty())
                                @include('partials.field', ['name' => 'startup_uuid', 'label' => 'On behalf of', 'type' => 'select', 'placeholder' => 'Just me',
                                    'options' => $memberships->mapWithKeys(fn ($m) => [$m->startup_uuid => $m->startup->display_name])->all(), 'value' => $activeStartup?->uuid])
                            @endif
                            @include('partials.field', ['name' => 'message', 'label' => 'Message to the organiser (optional)', 'type' => 'textarea', 'rows' => 2])
                            <p class="hint">The organiser sees your name, email and the startup you choose.</p>
                            <button type="submit" class="btn btn-primary" @disabled($left === 0)>{{ $opportunity->type === 'event' ? 'Register' : 'Send' }}</button>
                        </form>
                    @endif
                </section>
            @endif
        @endauth
    </article>
@endsection
