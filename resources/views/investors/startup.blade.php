@extends('layouts.public', ['pageTitle' => $startup->display_name.' · Investor view'])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="container section narrow">
        <p><a href="{{ route('investor.dashboard') }}">← Investor dashboard</a></p>
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
                    @if ($profile?->contact_email)<li>Company: <a href="mailto:{{ $profile->contact_email }}">{{ $profile->contact_email }}</a>@if ($profile->contact_phone) · {{ $profile->contact_phone }}@endif</li>@endif
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
