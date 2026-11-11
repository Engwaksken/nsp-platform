{{--
    Signed-in member area: left sidebar + top bar (like the admin area), no marketing chrome.
    Pages extend it with ['pageTitle' => ...] and put their content in @section('content').
    Startup pages pass $startup so the sidebar's "My startup" group shows that startup.
    The sidebar is built by App\Support\MemberNavigation, which lists only links the member can open.
--}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="no-js">
<head>
    @include('partials.head')
    <meta name="robots" content="noindex">
    @stack('head')
</head>
<body class="has-app-shell">
<a class="skip-link" href="#main">Skip to main content</a>
@php
    $member = auth()->user();
    $nav = \App\Support\MemberNavigation::for($member, ($startup ?? null) instanceof \App\Models\Registry\Startup ? $startup : null);
    $navGroups = $nav->groups();
    $initials = collect(preg_split('/\s+/', trim($member->name)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('') ?: '?';
@endphp
<div class="admin-shell app-shell">
    <aside class="admin-sidebar app-sidebar" id="app-sidebar" data-offcanvas aria-label="Member navigation">
        <div class="app-sidebar-head">
            <a class="brand" href="{{ route('workspace') }}">
                @if ($brandLogo)
                    <img src="{{ $brandLogo->url() }}" alt="{{ $brandLogo->alt_text ?: $brand['site_name'] }}" width="140" height="36">
                @else
                    {{ $brand['site_name'] }}
                @endif
            </a>
            <button type="button" class="app-sidebar-close" data-offcanvas-close aria-label="Close menu"><i class="fas fa-xmark" aria-hidden="true"></i></button>
            {{-- Without JavaScript on small screens the menu opens in place via #app-sidebar (see "no-js" in nsp.css). --}}
            <a class="app-sidebar-close nojs-only" href="#main" aria-label="Close menu"><i class="fas fa-xmark" aria-hidden="true"></i></a>
        </div>

        @if ($nav->startup)
            <div class="app-context">
                <span class="app-context-label">Working on</span>
                <strong class="app-context-name">{{ $nav->startup->display_name }}</strong>
                <span class="app-context-role">{{ \App\Models\Registry\StartupMembership::ROLES[$nav->membership->role] ?? $nav->membership->role }}</span>
                @if ($nav->otherStartups->isNotEmpty())
                    <details class="app-switcher">
                        <summary>Switch startup</summary>
                        <ul>
                            @foreach ($nav->otherStartups as $other)
                                <li>
                                    <form method="post" action="{{ route('workspace.startups.switch', $other) }}">
                                        @csrf
                                        <button type="submit">{{ $other->display_name }}</button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            </div>
        @endif

        <nav aria-label="Member">
            @foreach ($navGroups as $group)
                @if ($group['key'] === 'overview')
                    <ul class="app-nav-top">
                        @foreach ($group['links'] as $link)
                            @include('layouts.partials.app-nav-link')
                        @endforeach
                    </ul>
                @else
                    <details class="nav-group" data-nav-group="{{ $group['key'] }}" @if (in_array($group['key'], ['startup', 'grow'], true) || \App\Support\MemberNavigation::isActive($group)) open @endif>
                        <summary class="nav-heading"><i class="fas {{ $group['icon'] }}" aria-hidden="true"></i><span>{{ $group['label'] }}</span></summary>
                        <ul>
                            @foreach ($group['links'] as $link)
                                @include('layouts.partials.app-nav-link')
                            @endforeach
                        </ul>
                    </details>
                @endif
            @endforeach
            @if ($member->canUseAdmin())
                <ul class="app-nav-foot">
                    <li><a href="{{ route('admin.dashboard') }}"><i class="fas fa-screwdriver-wrench nav-item-icon" aria-hidden="true"></i><span>Admin area</span></a></li>
                </ul>
            @endif
        </nav>
    </aside>
    <div class="shell-backdrop" data-offcanvas-close hidden></div>

    <div class="admin-main app-main">
        <header class="admin-topbar app-topbar">
            <div class="app-topbar-start">
                <button type="button" class="nav-toggle btn btn-secondary btn-sm" data-toggle-target="app-sidebar" aria-expanded="false" aria-controls="app-sidebar">Menu</button>
                <a class="nav-toggle-nojs nojs-only btn btn-secondary btn-sm" href="#app-sidebar">Menu</a>
                <a class="app-topbar-brand" href="{{ route('workspace') }}">{{ $brand['site_name'] }}</a>
                @hasSection('breadcrumbs')
                    <nav class="breadcrumbs" aria-label="Breadcrumb"><ol>@yield('breadcrumbs')</ol></nav>
                @endif
            </div>
            <div class="app-topbar-end">
                <a class="topbar-icon" href="{{ route('home') }}" title="Go to the public site">
                    <i class="fas fa-globe" aria-hidden="true"></i><span class="topbar-label">Public site</span>
                </a>
                <a class="topbar-icon" href="{{ route('notifications.index') }}" title="Notifications" @if (request()->routeIs('notifications.index')) aria-current="page" @endif>
                    <i class="fas fa-bell" aria-hidden="true"></i>
                    <span class="visually-hidden">Notifications{{ $nav->unread ? ' ('.$nav->unread.' unread)' : '' }}</span>
                    @if ($nav->unread)<span class="topbar-badge" aria-hidden="true">{{ $nav->unread > 99 ? '99+' : $nav->unread }}</span>@endif
                </a>
                <div class="nav-group app-user">
                    <button type="button" class="nav-group-toggle app-user-toggle" aria-expanded="false" aria-controls="app-user-menu">
                        <span class="avatar" aria-hidden="true">{{ $initials }}</span>
                        <span class="app-user-name">{{ $member->name }}</span>
                        <span class="visually-hidden">: account menu</span>
                    </button>
                    <ul class="nav-dropdown nav-dropdown-end" id="app-user-menu" data-fixed-align>
                        <li class="nav-dropdown-head">Signed in as <strong>{{ $member->name }}</strong><br><span>{{ $member->email }}</span></li>
                        <li><a href="{{ route('workspace') }}">Dashboard</a></li>
                        <li><a href="{{ route('account.edit') }}">My profile</a></li>
                        <li><a href="{{ route('mfa.setup') }}">Sign-in security</a></li>
                        <li><a href="{{ route('notifications.preferences') }}">Notification settings</a></li>
                        @if ($member->canUseAdmin())
                            <li><a href="{{ route('admin.dashboard') }}">Admin area</a></li>
                        @endif
                        <li><a href="{{ route('home') }}">Public site</a></li>
                        <li class="theme-switch-item"><span aria-hidden="true">Theme</span>@include('partials.theme-switch')</li>
                        <li class="nav-dropdown-foot">
                            <form method="post" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="btn btn-secondary btn-sm">Sign out</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </header>
        <main id="main" class="admin-content app-content" tabindex="-1">
            @yield('content')
        </main>
        <footer class="app-footer">
            <span>&copy; {{ now()->year }} {{ $brand['site_name'] }}</span>
            <a href="{{ route('home') }}">Public site</a>
            @if (\Illuminate\Support\Facades\Route::has('contact'))<a href="{{ route('contact') }}">Contact</a>@endif
        </footer>
    </div>
</div>
</body>
</html>
