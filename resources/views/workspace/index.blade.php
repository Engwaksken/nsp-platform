@extends('layouts.app', ['pageTitle' => 'My workspace'])

@section('content')
    @php($hour = (int) now()->format('G'))
    @php($firstName = \Illuminate\Support\Str::of($user->name)->trim()->explode(' ')->first() ?: $user->name)
    @php($noStartups = is_array($startups) && $startups === [])

    <section class="dash-hero" aria-labelledby="dash-title">
        <div>
            <span class="eyebrow">{{ now()->format('l j F') }}</span>
            <h1 id="dash-title">{{ $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening') }}, {{ $firstName }}</h1>
            <p>
                @if ($active)
                    You are working on <strong>{{ $active->startup->display_name }}</strong>. Here is what needs your attention.
                @else
                    Welcome to your workspace. Services appear here as they become available.
                @endif
            </p>
        </div>
        <div class="actions">
            @if ($active)
                <a class="btn btn-primary" href="{{ route('workspace.startups.overview', $active->startup) }}">Open {{ $active->startup->display_name }}</a>
            @elseif (\App\Support\Features::enabled('registry'))
                <a class="btn btn-primary" href="{{ route('workspace.startups.create') }}">Register a startup</a>
            @endif
            <a class="btn btn-secondary" href="{{ route('notifications.index') }}">Notifications</a>
        </div>
    </section>

    @include('partials.flash')
    @if ($registryMessage)
        <div class="alert alert-warning" role="status">{{ $registryMessage }}</div>
    @endif

    @include('partials.stats', ['label' => 'Your figures', 'stats' => $stats])

    <div class="dash-layout">
        <div>
            @if ($noStartups)
                <section class="empty-state dash-block" aria-labelledby="start-heading">
                    <span class="empty-icon" aria-hidden="true"><i class="fas fa-rocket"></i></span>
                    <h2 id="start-heading">Put your startup on the map</h2>
                    <p>You are not on a startup team yet. Register your startup to get matched with opportunities, investors, experts and talent. Joining an existing startup? Ask a co-founder to invite you by email.</p>
                    <div class="actions">
                        <a class="btn btn-primary" href="{{ route('workspace.startups.create') }}">Register your startup</a>
                        <a class="btn btn-secondary" href="{{ route('workspace.startups.index') }}">My startups</a>
                    </div>
                </section>
            @endif

            @if ($next)
                <section class="panel dash-block" aria-labelledby="next-heading">
                    <h2 id="next-heading">Next steps</h2>
                    <ul class="dash-next">
                        @foreach ($next as [$text, $label, $url])
                            <li><span>{{ $text }}</span> <a class="btn btn-primary btn-sm" href="{{ $url }}">{{ $label }}</a></li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($actions)
                <section class="dash-block" aria-labelledby="actions-heading">
                    <div class="section-head"><h2 id="actions-heading">Quick actions</h2></div>
                    <ul class="action-tiles">
                        @foreach ($actions as [$icon, $title, $text, $url, $tone])
                            <li>
                                <a class="action-tile tone-{{ $tone }}" href="{{ $url }}">
                                    <span class="tile-icon" aria-hidden="true"><i class="fas {{ $icon }}"></i></span>
                                    <span><strong>{{ $title }}</strong><span class="tile-text">{{ $text }}</span></span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <section class="dash-block" aria-labelledby="services-heading">
                <div class="section-head"><h2 id="services-heading">Services</h2></div>
                {{-- Open by default (a visitor's own open/closed choice is remembered); closed, a card
                     shows its name and description, open it also shows its buttons. --}}
                <x-card-grid label="Services" min="240px" remember="services">
                    @foreach ($sections as $section)
                        <x-collapsible-card id="ws-{{ \Illuminate\Support\Str::slug($section['title']) }}" icon="{{ $section['icon'] }}"
                            class="tone-{{ $section['tone'] }} {{ $section['available'] ? '' : 'is-soon' }}"
                            :title="$section['title']" :badge="$section['available'] ? null : 'Not available yet'" badge-tone="soon"
                            :summary="$section['text']" :open="true">
                            @if ($section['available'] && $section['actions'])
                                <div class="actions">
                                    @foreach ($section['actions'] as [$label, $url, $primary])
                                        <a class="btn {{ $primary ? 'btn-primary' : 'btn-secondary' }} btn-sm" href="{{ $url }}">{{ $label }}</a>
                                    @endforeach
                                </div>
                            @elseif ($section['available'])
                                <p class="meta">Register or join a startup to use this.</p>
                            @else
                                <p class="meta">This service is coming soon.</p>
                            @endif
                        </x-collapsible-card>
                    @endforeach
                </x-card-grid>
            </section>
        </div>

        <aside aria-label="Your startups and roles">
            @if (is_array($startups) && $startups !== [])
                <section class="panel" aria-labelledby="startups-heading">
                    <div class="section-head">
                        <h2 id="startups-heading">Your startups</h2>
                        <a href="{{ route('workspace.startups.create') }}">Register another</a>
                    </div>
                    <ul class="list-rows">
                        @foreach ($startups as $row)
                            <li>
                                <span>
                                    <a href="{{ route('workspace.startups.overview', $row['uuid']) }}">{{ $row['display_name'] }}</a>
                                    @if ($active && $active->startup_uuid === $row['uuid'])<span class="meta">Current startup</span>@endif
                                </span>
                                <span class="badge">{{ \App\Models\Registry\StartupMembership::ROLES[$row['role']] ?? $row['role'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($roles)
                <section class="panel" aria-labelledby="roles-heading">
                    <h2 id="roles-heading">Your other roles</h2>
                    <ul class="list-rows">
                        @foreach ($roles as [$icon, $title, $status, $url])
                            <li><span><a href="{{ $url }}"><i class="fas {{ $icon }}" aria-hidden="true"></i> {{ $title }}</a><span class="meta">{{ $status }}</span></span></li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($join)
                <section class="panel" aria-labelledby="join-heading">
                    <h2 id="join-heading">More ways to take part</h2>
                    <ul class="list-rows">
                        @foreach ($join as [$icon, $title, $text, $url])
                            <li><span><a href="{{ $url }}"><i class="fas {{ $icon }}" aria-hidden="true"></i> {{ $title }}</a><span class="meta">{{ $text }}</span></span></li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </aside>
    </div>
@endsection
