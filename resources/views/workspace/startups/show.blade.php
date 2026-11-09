@extends('layouts.workspace', ['pageTitle' => $startup->display_name])

@push('head')<meta name="robots" content="noindex">@endpush

@section('workspace')
    @php
        $role = $membership->role;
        $canSee = function (string $group) use ($profile, $membership): bool {
            $level = $profile->visibilityOf($group);
            return $membership->canEdit() || in_array($level, ['members', 'ecosystem'], true);
        };
        $hidden = 'Only owners, founders and admins can see this.';
    @endphp
    <div class="container section">
        <div class="page-head">
            <h1>{{ $startup->display_name }}</h1>
            @if ($membership->canEdit())<a class="btn btn-primary" href="{{ route('workspace.startups.edit', $startup) }}">Edit profile</a>@endif
        </div>
        <p class="meta">You are {{ \App\Models\Registry\StartupMembership::ROLES[$role] }} · Hive startup ID <code>{{ $startup->uuid }}</code></p>
        @include('partials.flash')

        @include('partials.stats', ['label' => 'Profile at a glance', 'stats' => [
            ['icon' => 'fa-id-card', 'tone' => 'blue', 'label' => 'Profile complete', 'value' => ($profile->profile_completion ?? 0).'%', 'url' => $membership->canEdit() ? route('workspace.startups.edit', $startup) : null],
            ['icon' => 'fa-users', 'tone' => 'purple', 'label' => 'Team members', 'value' => $startup->activeMemberships->count(), 'url' => route('workspace.startups.team', $startup)],
            ['icon' => 'fa-industry', 'tone' => 'green', 'label' => 'Sector', 'value' => $profile->sector ?: 'Not given'],
            ['icon' => 'fa-calendar-days', 'tone' => 'amber', 'label' => 'Founded', 'value' => $startup->year_founded ? (string) $startup->year_founded : 'Not given'],
        ]])

        <div data-tabs="Profile sections" data-tabs-key="profile">
            <section class="panel" aria-labelledby="company-heading" data-tab="Company" data-tab-icon="fa-building">
                <h2 id="company-heading">Company</h2>
                <dl>
                    <dt>Registered name</dt><dd>{{ $startup->legal_name }}</dd>
                    <dt>Registration number</dt><dd>{{ $startup->registration_number ?: 'Not given' }}</dd>
                    <dt>Country</dt><dd>{{ \App\Models\Registry\Startup::COUNTRIES[$startup->country] ?? $startup->country }}</dd>
                    <dt>Founded</dt><dd>{{ $startup->year_founded ?: 'Not given' }}</dd>
                    <dt>Website</dt><dd>@if ($startup->website)<a href="{{ $startup->website }}" rel="noopener" target="_blank">{{ $startup->website }}</a>@else Not given @endif</dd>
                    <dt>Sector</dt><dd>{{ $profile->sector ?: 'Not given' }}{{ $profile->sub_sector ? ' · '.$profile->sub_sector : '' }}</dd>
                    <dt>Based in</dt><dd>{{ $profile->headquarters_location ?: 'Not given' }}</dd>
                    <dt>Team size</dt><dd>{{ $profile->team_size ?: 'Not given' }}</dd>
                </dl>
                @if ($startup->description)<h3>About</h3><p>{!! nl2br(e($startup->description)) !!}</p>@endif
            </section>

            <section class="panel" aria-labelledby="private-heading" data-tab="Private details" data-tab-icon="fa-lock">
                <h2 id="private-heading">Private details</h2>
                <dl>
                    <dt>Yearly revenue</dt>
                    <dd>{{ $canSee('financials') ? (\App\Models\Registry\StartupProfile::REVENUE_BANDS[$profile->revenue_band] ?? 'Not given') : $hidden }}</dd>
                    <dt>Funding raised</dt>
                    <dd>{{ $canSee('financials') ? ($profile->funding_raised_amount !== null && $profile->funding_raised_amount !== '' ? number_format((float) $profile->funding_raised_amount).' '.$profile->funding_currency : 'Not given') : $hidden }}</dd>
                    <dt>Traction</dt>
                    <dd>{!! $canSee('traction') ? ($profile->traction_summary ? nl2br(e($profile->traction_summary)) : 'Not given') : e($hidden) !!}</dd>
                    <dt>Contact</dt>
                    <dd>{{ $canSee('contact') ? trim(($profile->contact_email ?: '').' '.($profile->contact_phone ?: '')) ?: 'Not given' : $hidden }}</dd>
                </dl>
            </section>

            <section class="panel" aria-labelledby="team-heading" data-tab="Team" data-tab-icon="fa-users" data-tab-count="{{ $startup->activeMemberships->count() }}">
                <h2 id="team-heading">Team</h2>
                <ul>
                    @foreach ($startup->activeMemberships as $member)
                        <li>{{ $member->user?->name }} · {{ \App\Models\Registry\StartupMembership::ROLES[$member->role] }}</li>
                    @endforeach
                </ul>
                <a href="{{ route('workspace.startups.team', $startup) }}">Manage the team</a>
            </section>
        </div>
    </div>
@endsection
