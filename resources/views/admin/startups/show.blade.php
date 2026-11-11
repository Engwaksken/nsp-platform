@extends('layouts.admin')
@section('title', $startup->display_name)

@section('breadcrumbs')
    <li><a href="{{ route('admin.startups.index') }}">Startups</a></li>
    <li aria-current="page">{{ $startup->display_name }}</li>
@endsection

@section('content')
    @php($profile = $startup->profile ?? new \App\Models\Registry\StartupProfile)
    @php($staffMay = fn (string $group) => $profile->visibilityOf($group) === 'ecosystem')
    <div class="page-head">
        <h1>{{ $startup->display_name }} <span class="badge badge-{{ $startup->status === 'active' ? 'on' : 'off' }}">{{ ucfirst($startup->status) }}</span></h1>
        @if ($startup->status !== 'merged')
            <form method="post" action="{{ route('admin.startups.status', $startup) }}" data-confirm="{{ $startup->status === 'active' ? 'Archive this startup? Its team will no longer see it.' : 'Restore this startup?' }}">
                @csrf @method('PUT')
                <input type="hidden" name="status" value="{{ $startup->status === 'active' ? 'archived' : 'active' }}">
                <button type="submit" class="btn btn-secondary">{{ $startup->status === 'active' ? 'Archive' : 'Restore' }}</button>
            </form>
        @endif
    </div>
    <p class="hint">Hive startup ID <code>{{ $startup->uuid }}</code> · version {{ $startup->version }}. Viewing this page is recorded in the audit log.
        @if ($startup->merged_into_uuid) Merged into <a href="{{ route('admin.startups.show', $startup->merged_into_uuid) }}">another startup</a>.@endif</p>

    <div class="form-row">
        <section class="panel">
            <h2>Company</h2>
            <dl>
                <dt>Registered name</dt><dd>{{ $startup->legal_name }}</dd>
                <dt>Registration number</dt><dd>@if ($startup->registration_number){{ $startup->registration_number }}@else<span class="meta">Not given</span>@endif</dd>
                <dt>Country · founded</dt><dd>{{ $startup->country }} · @if ($startup->year_founded){{ $startup->year_founded }}@else<span class="meta">Not given</span>@endif</dd>
                <dt>Website</dt><dd>@if ($startup->website){{ $startup->website }}@else<span class="meta">Not given</span>@endif</dd>
                <dt>Sector</dt><dd>@if ($profile->sector){{ $profile->sector }}@else<span class="meta">Not given</span>@endif</dd>
                <dt>Based in · team</dt><dd>@if ($profile->headquarters_location){{ $profile->headquarters_location }}@else<span class="meta">Not given</span>@endif · @if ($profile->team_size){{ $profile->team_size }}@else<span class="meta">Not given</span>@endif</dd>
            </dl>
        </section>
        <section class="panel">
            <h2>Private details</h2>
            <p class="hint">Shown only where the startup chose “our team and Hive staff”.</p>
            <dl>
                <dt>Revenue and funding</dt><dd>{{ $staffMay('financials') ? ((\App\Models\Registry\StartupProfile::REVENUE_BANDS[$profile->revenue_band] ?? 'Not given').' · '.($profile->funding_raised_amount ? number_format((float) $profile->funding_raised_amount).' '.$profile->funding_currency : 'Not given')) : 'Not shared with Hive staff' }}</dd>
                <dt>Traction</dt><dd>{{ $staffMay('traction') ? ($profile->traction_summary ?: 'Not given') : 'Not shared with Hive staff' }}</dd>
                <dt>Contact</dt><dd>{{ $staffMay('contact') ? trim($profile->contact_email.' '.$profile->contact_phone) ?: 'Not given' : 'Not shared with Hive staff' }}</dd>
            </dl>
        </section>
    </div>

    <section class="panel">
        <h2>Team</h2>
        <ul>
            @foreach ($startup->memberships->whereIn('status', ['active', 'invited', 'requested']) as $member)
                <li>{{ $member->user?->name ?? $member->invited_email }} · {{ \App\Models\Registry\StartupMembership::ROLES[$member->role] }} · {{ $member->status }}</li>
            @endforeach
        </ul>
    </section>

    <section class="panel">
        <h2>Data sharing</h2>
        <ul>
            @forelse ($startup->consents->whereNull('revoked_at') as $consent)
                <li>{{ config('nsp.registry.consent_purposes.'.$consent->purpose.'.label', $consent->purpose) }} (since {{ $consent->granted_at->format('j M Y') }})</li>
            @empty
                <li>Nothing shared.</li>
            @endforelse
        </ul>
    </section>

    <section class="panel">
        <h2>Linked records in other systems</h2>
        <ul>
            @forelse ($startup->aliases as $alias)
                <li>{{ \App\Models\Registry\StartupAlias::SOURCES[$alias->source_system] ?? $alias->source_system }}: <code>{{ $alias->external_id }}</code></li>
            @empty
                <li>None.</li>
            @endforelse
        </ul>
        <form method="post" action="{{ route('admin.startups.aliases', $startup) }}" class="filters">
            @csrf
            @include('partials.field', ['name' => 'source_system', 'label' => 'System', 'type' => 'select', 'options' => \App\Models\Registry\StartupAlias::SOURCES, 'value' => 'ims'])
            @include('partials.field', ['name' => 'external_id', 'label' => 'ID in that system', 'required' => true, 'maxlength' => 100, 'hint' => 'For example an IMS application ID. Only link when you have confirmed it is the same company.'])
            <button type="submit" class="btn btn-secondary">Link</button>
        </form>
    </section>

    @if ($merges->isNotEmpty())
        <section class="panel">
            <h2>Merges</h2>
            <ul>
                @foreach ($merges as $merge)
                    <li>{{ $merge->source?->display_name }} <i class="fas fa-arrow-right" aria-hidden="true"></i><span class="visually-hidden">to</span> {{ $merge->target?->display_name }} on {{ $merge->merged_at->format('j M Y') }} by {{ $merge->merger?->name ?? 'an unknown user' }}@if ($merge->undone_at) (undone {{ $merge->undone_at->format('j M Y') }})@endif</li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="panel">
        <h2>Recent sync events</h2>
        <p class="hint">Events waiting in the outbox for the IMS integration (Phase 3). They carry no private values.</p>
        <ul>
            @forelse ($events as $event)
                <li><code>{{ $event->type }}</code> · {{ $event->occurred_at->format('j M Y H:i') }} · {{ $event->published_at ? 'sent' : 'waiting' }}</li>
            @empty
                <li>None.</li>
            @endforelse
        </ul>
    </section>
@endsection
