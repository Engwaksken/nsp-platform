@extends('layouts.workspace', ['pageTitle' => $startup->display_name])

@push('head')<meta name="robots" content="noindex">@endpush

@section('workspace')
    @php
        $roles = \App\Models\Registry\StartupMembership::ROLES;
        $role = $membership->role;
        $canSee = function (string $group) use ($profile, $membership): bool {
            $level = $profile->visibilityOf($group);
            return $membership->canEdit() || in_array($level, ['members', 'ecosystem'], true);
        };
        $levelShort = ['editors' => 'Owners, founders and admins', 'members' => 'Whole team', 'ecosystem' => 'Team and Hive staff'];
        $levelIcon = ['editors' => 'fa-lock', 'members' => 'fa-user-group', 'ecosystem' => 'fa-building-shield'];
        $initials = fn (?string $name) => collect(preg_split('/\s+/', trim((string) $name)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('') ?: '?';
        $country = \App\Models\Registry\Startup::COUNTRIES[$startup->country] ?? $startup->country;
        $notGiven = '<span class="detail-empty">Not given</span>';
        $completion = (int) ($profile->profile_completion ?? 0);
    @endphp

    <header class="profile-hero">
        <span class="profile-hero-mark" aria-hidden="true">{{ $initials($startup->display_name) }}</span>
        <div class="profile-hero-body">
            <p class="profile-hero-eyebrow">Startup profile</p>
            <h1>{{ $startup->display_name }}</h1>
            @if ($startup->legal_name && $startup->legal_name !== $startup->display_name)
                <p class="profile-hero-legal">{{ $startup->legal_name }}</p>
            @endif
            <ul class="profile-hero-tags" aria-label="Key facts">
                @if ($profile->sector)<li><i class="fas fa-industry" aria-hidden="true"></i> {{ $profile->sector }}</li>@endif
                @if ($country)<li><i class="fas fa-location-dot" aria-hidden="true"></i> {{ $profile->headquarters_location ? $profile->headquarters_location.', ' : '' }}{{ $country }}</li>@endif
                @if ($startup->year_founded)<li><i class="fas fa-calendar" aria-hidden="true"></i> Founded {{ $startup->year_founded }}</li>@endif
                @if ($startup->website)<li><i class="fas fa-globe" aria-hidden="true"></i> <a href="{{ $startup->website }}" rel="noopener" target="_blank">{{ preg_replace('#^https?://(www\.)?#', '', rtrim($startup->website, '/')) }}<span class="visually-hidden"> (opens in a new tab)</span></a></li>@endif
            </ul>
        </div>
        <div class="profile-hero-actions">
            @if ($membership->canEdit())
                <a class="btn btn-primary" href="{{ route('workspace.startups.edit', $startup) }}"><i class="fas fa-pen" aria-hidden="true"></i> Edit profile</a>
            @endif
            <a class="btn btn-secondary" href="{{ route('workspace.startups.team', $startup) }}"><i class="fas fa-users" aria-hidden="true"></i> Team</a>
        </div>
        <div class="profile-hero-meter">
            <div class="meter-label"><span>Profile complete</span><strong>{{ $completion }}%</strong></div>
            <div class="meter" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $completion }}" aria-label="Profile complete"><span style="width: {{ $completion }}%"></span></div>
            <p class="meta">You are {{ $roles[$role] ?? $role }} · ID <code>{{ \Illuminate\Support\Str::limit($startup->uuid, 13, '…') }}</code></p>
        </div>
    </header>

    @include('partials.flash')

    <div data-tabs="Profile sections" data-tabs-key="profile">
        <section class="panel profile-section" aria-labelledby="company-heading" data-tab="Company" data-tab-icon="fa-building">
            <h2 id="company-heading">Company details</h2>
            <dl class="detail-grid">
                <div><dt>Registered name</dt><dd>{{ $startup->legal_name }}</dd></div>
                <div><dt>Trading name</dt><dd>{{ $startup->display_name }}</dd></div>
                <div><dt>Registration number</dt><dd>{!! $startup->registration_number ? e($startup->registration_number) : $notGiven !!}</dd></div>
                <div><dt>Country</dt><dd>{{ $country }}</dd></div>
                <div><dt>Based in</dt><dd>{!! $profile->headquarters_location ? e($profile->headquarters_location) : $notGiven !!}</dd></div>
                <div><dt>Year founded</dt><dd>{!! $startup->year_founded ? e($startup->year_founded) : $notGiven !!}</dd></div>
                <div><dt>Sector</dt><dd>{!! $profile->sector ? e($profile->sector).($profile->sub_sector ? ' <span class="detail-sub">· '.e($profile->sub_sector).'</span>' : '') : $notGiven !!}</dd></div>
                <div><dt>Team size</dt><dd>{!! $profile->team_size ? e($profile->team_size).' '.\Illuminate\Support\Str::plural('person', (int) $profile->team_size) : $notGiven !!}</dd></div>
                <div class="detail-wide"><dt>Website</dt><dd>@if ($startup->website)<a href="{{ $startup->website }}" rel="noopener" target="_blank">{{ $startup->website }}</a>@else{!! $notGiven !!}@endif</dd></div>
            </dl>
            <h3 class="detail-heading">About</h3>
            @if ($startup->description)
                <p class="detail-about">{!! nl2br(e($startup->description)) !!}</p>
            @else
                <p class="hint">No description yet.@if ($membership->canEdit()) <a href="{{ route('workspace.startups.edit', $startup) }}">Add one</a> so programmes and investors understand what you do.@endif</p>
            @endif
        </section>

        <section class="panel profile-section" aria-labelledby="private-heading" data-tab="Private details" data-tab-icon="fa-lock">
            <h2 id="private-heading">Private details</h2>
            <p class="hint">Stored encrypted and never public. Each group shows who can see it.@if ($membership->canEdit()) Change this when you <a href="{{ route('workspace.startups.edit', $startup) }}">edit the profile</a>.@endif</p>
            <div class="private-groups">
                @foreach ([
                    'financials' => ['icon' => 'fa-sack-dollar', 'rows' => [
                        'Yearly revenue' => \App\Models\Registry\StartupProfile::REVENUE_BANDS[$profile->revenue_band] ?? null,
                        'Funding raised' => $profile->funding_raised_amount !== null && $profile->funding_raised_amount !== '' ? number_format((float) $profile->funding_raised_amount).' '.$profile->funding_currency : null,
                    ]],
                    'traction' => ['icon' => 'fa-chart-line', 'rows' => ['Traction' => $profile->traction_summary]],
                    'contact' => ['icon' => 'fa-address-card', 'rows' => ['Email' => $profile->contact_email, 'Phone' => $profile->contact_phone]],
                ] as $group => $info)
                    @php($level = $profile->visibilityOf($group))
                    <article class="private-group">
                        <header>
                            <span class="private-group-icon" aria-hidden="true"><i class="fas {{ $info['icon'] }}"></i></span>
                            <h3>{{ \App\Models\Registry\StartupProfile::VISIBILITY_GROUPS[$group]['label'] }}</h3>
                            <span class="visibility-pill" title="{{ \App\Models\Registry\StartupProfile::VISIBILITY_LEVELS[$level] ?? '' }}"><i class="fas {{ $levelIcon[$level] ?? 'fa-lock' }}" aria-hidden="true"></i> {{ $levelShort[$level] ?? $level }}</span>
                        </header>
                        @if ($canSee($group))
                            <dl class="detail-list">
                                @foreach ($info['rows'] as $label => $value)
                                    <div><dt>{{ $label }}</dt><dd>
                                        @if (blank($value)){!! $notGiven !!}
                                        @elseif ($label === 'Email')<a href="mailto:{{ $value }}">{{ $value }}</a>
                                        @elseif ($label === 'Phone')<a href="tel:{{ preg_replace('/[^0-9+]/', '', $value) }}">{{ $value }}</a>
                                        @else{!! nl2br(e($value)) !!}@endif
                                    </dd></div>
                                @endforeach
                            </dl>
                        @else
                            <p class="private-locked"><i class="fas fa-eye-slash" aria-hidden="true"></i> Only owners, founders and admins can see this.</p>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>

        <section class="panel profile-section" aria-labelledby="team-heading" data-tab="Team" data-tab-icon="fa-users" data-tab-count="{{ $startup->activeMemberships->count() }}">
            <div class="section-head">
                <h2 id="team-heading">Team</h2>
                <a class="btn btn-secondary btn-sm" href="{{ route('workspace.startups.team', $startup) }}">{{ $membership->canManageTeam() ? 'Manage the team' : 'View the team' }}</a>
            </div>
            <ul class="people-grid">
                @foreach ($startup->activeMemberships->sortBy(fn ($m) => array_search($m->role, array_keys($roles), true)) as $member)
                    <li class="person-card">
                        <span class="person-avatar" aria-hidden="true">{{ $initials($member->user?->name) }}</span>
                        <div class="person-body">
                            <strong>{{ $member->user?->name ?? 'Former member' }}@if ($member->user_id === auth()->id()) <span class="person-you">You</span>@endif</strong>
                            <span class="role-badge role-{{ $member->role }}">{{ $roles[$member->role] ?? $member->role }}</span>
                            @if ($member->accepted_at)<span class="meta">Joined {{ $member->accepted_at->format('M Y') }}</span>@endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>
@endsection
