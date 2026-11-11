<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="no-js">
<head>
    @include('partials.head')
    @stack('head')
</head>
<body>
<a class="skip-link" href="#main">Skip to main content</a>

@if (!empty($isPreview))
    <div class="preview-banner" role="status">Preview: this is how the page will look. It may not be published yet.</div>
@endif

<header class="site-header">
    <div class="container">
        <a class="brand" href="{{ route('home') }}">
            @if ($brandLogo)
                <img src="{{ $brandLogo->url() }}" alt="{{ $brandLogo->alt_text ?: $brand['site_name'] }}" width="160" height="40">
            @else
                {{ $brand['site_name'] }}
            @endif
        </a>
        <button type="button" class="nav-toggle" data-toggle-target="site-nav" aria-expanded="false" aria-controls="site-nav">Menu</button>
        @php
            $menuItems = $headerMenu?->items->filter->isVisible() ?? collect();
            // App sections grouped into dropdowns; a group with one enabled link shows as a plain link.
            $navGroups = collect([
                'Opportunities' => [
                    ['feature' => 'opportunities', 'route' => 'opportunities.index', 'active' => 'opportunities.*', 'title' => 'Opportunities and events', 'text' => 'Grants, accelerators, competitions and events'],
                    ['feature' => 'challenges', 'route' => 'challenges.index', 'active' => 'challenges.*', 'title' => 'Challenge Exchange', 'text' => 'Innovation and market challenges to solve'],
                    ['feature' => 'talent', 'route' => 'jobs.index', 'active' => 'jobs.*', 'title' => 'Jobs', 'text' => 'Roles at startups in the ecosystem'],
                ],
                'Ecosystem' => [
                    ['feature' => 'marketplace', 'route' => 'directory.index', 'active' => 'directory.*', 'title' => 'Startup directory', 'text' => 'Startups that chose to be listed'],
                    ['feature' => 'marketplace', 'route' => 'marketplace.index', 'active' => 'marketplace.*', 'title' => 'Marketplace', 'text' => 'Products, services and partnerships'],
                    ['feature' => 'knowledge', 'route' => 'knowledge.index', 'active' => 'knowledge.*', 'title' => 'Knowledge Hub', 'text' => 'Research, policy briefs, templates and toolkits'],
                ],
            ])->map(fn ($links) => collect($links)->filter(fn ($link) => \App\Support\Features::enabled($link['feature']) && \Illuminate\Support\Facades\Route::has($link['route'])))
              ->filter(fn ($links) => $links->isNotEmpty());
            // Footer: "Explore" lists the same app sections; "Information" merges the top-level header links and the footer menu (no duplicates, no home link).
            $exploreLinks = $navGroups->flatten(1);
            $infoLinks = $menuItems->whereNull('parent_id')->concat($footerMenu?->items->filter->isVisible() ?? [])
                ->reject(fn ($item) => trim(url($item->href()), '/') === trim(url('/'), '/'))
                ->unique(fn ($item) => trim(url($item->href()), '/'));
            $socialLinks = \App\Support\Settings::json('social_links');
            // Phone tab bar: "Explore" opens the first enabled app section.
            $exploreTab = $exploreLinks->first();
            $tabIcon = fn (string $paths) => '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'.$paths.'</svg>';
        @endphp
        <nav class="site-nav" id="site-nav" aria-label="Main">
            <ul class="nav-links">
                @foreach ($menuItems->whereNull('parent_id') as $item)
                    @php($children = $menuItems->where('parent_id', $item->id))
                    @if ($children->isNotEmpty())
                        <li class="nav-group">
                            <button type="button" class="nav-group-toggle" aria-expanded="false" aria-controls="nav-menu-{{ $item->id }}">{{ $item->label }}</button>
                            <ul class="nav-dropdown" id="nav-menu-{{ $item->id }}">
                                @foreach ($children as $child)
                                    <li><a href="{{ $child->href() }}" @if (url()->current() === $child->href()) aria-current="page" @endif
                                           @if ($child->open_in_new_tab) target="_blank" rel="noopener" @endif>{{ $child->label }}</a></li>
                                @endforeach
                            </ul>
                        </li>
                    @else
                        <li><a class="nav-link" href="{{ $item->href() }}" @if (url()->current() === $item->href()) aria-current="page" @endif
                               @if ($item->open_in_new_tab) target="_blank" rel="noopener" @endif>{{ $item->label }}</a></li>
                    @endif
                @endforeach
                @foreach ($navGroups as $groupLabel => $links)
                    @if ($links->count() === 1)
                        @php($link = $links->first())
                        <li><a class="nav-link" href="{{ route($link['route']) }}" @if (request()->routeIs($link['active'])) aria-current="page" @endif>{{ $link['title'] }}</a></li>
                    @else
                        @php($groupId = 'nav-menu-'.\Illuminate\Support\Str::slug($groupLabel))
                        <li class="nav-group">
                            <button type="button" class="nav-group-toggle @if ($links->contains(fn ($link) => request()->routeIs($link['active']))) is-current @endif" aria-expanded="false" aria-controls="{{ $groupId }}">{{ $groupLabel }}</button>
                            <ul class="nav-dropdown nav-dropdown-wide" id="{{ $groupId }}">
                                @foreach ($links as $link)
                                    <li><a href="{{ route($link['route']) }}" @if (request()->routeIs($link['active'])) aria-current="page" @endif>
                                        <span class="nav-item-title">{{ $link['title'] }}</span>
                                        <span class="nav-item-text">{{ $link['text'] }}</span>
                                    </a></li>
                                @endforeach
                            </ul>
                        </li>
                    @endif
                @endforeach
            </ul>
            <div class="nav-theme">@include('partials.theme-switch')</div>
            @auth
                @php($unreadNotifications = \App\Models\Communication\UserNotification::query()->where('user_id', auth()->id())->whereNull('read_at')->count())
                {{-- Signed in: the main call to action is the dashboard (admins go to the admin dashboard). --}}
                <a class="btn btn-primary btn-sm nav-signin nav-dashboard" href="{{ auth()->user()->canUseAdmin() ? route('admin.dashboard') : route('workspace') }}"><i class="fas fa-gauge-high" aria-hidden="true"></i> Go to dashboard</a>
                <div class="nav-group nav-account">
                    <button type="button" class="nav-group-toggle nav-account-toggle" aria-expanded="false" aria-controls="nav-menu-account">
                        Account
                        @if ($unreadNotifications)<span class="nav-dot" aria-hidden="true"></span><span class="visually-hidden"> ({{ $unreadNotifications }} unread notifications)</span>@endif
                    </button>
                    <ul class="nav-dropdown nav-dropdown-end" id="nav-menu-account" data-fixed-align>
                        <li class="nav-dropdown-head">Signed in as <strong>{{ auth()->user()->name }}</strong></li>
                        <li><a href="{{ route('workspace') }}" @if (request()->routeIs('workspace')) aria-current="page" @endif>My workspace</a></li>
                        <li><a href="{{ route('notifications.index') }}" @if (request()->routeIs('notifications.*')) aria-current="page" @endif>Notifications @if ($unreadNotifications)<span class="badge">{{ $unreadNotifications }}<span class="visually-hidden"> unread</span></span>@endif</a></li>
                        <li><a href="{{ route('mfa.setup') }}" @if (request()->routeIs('mfa.*')) aria-current="page" @endif>Security</a></li>
                        @if (auth()->user()->canUseAdmin())
                            <li><a href="{{ route('admin.dashboard') }}">Admin</a></li>
                        @endif
                        <li class="nav-dropdown-foot">
                            <form method="post" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="btn btn-secondary btn-sm">Sign out</button>
                            </form>
                        </li>
                    </ul>
                </div>
            @else
                <a class="btn btn-primary btn-sm nav-signin" href="{{ route('login') }}">Sign in</a>
            @endauth
        </nav>
    </div>
</header>

<main id="main" tabindex="-1">
    @yield('content')
</main>

{{-- Phones (768px and below): app-style tab bar fixed to the bottom of the screen. --}}
<nav class="app-tabbar" aria-label="App">
    <a href="{{ route('home') }}" @if (request()->routeIs('home')) aria-current="page" @endif>
        {!! $tabIcon('<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h5v-6h4v6h5V9.5"/>') !!}<span>Home</span>
    </a>
    @if ($exploreTab)
        <a href="{{ route($exploreTab['route']) }}" @if ($exploreLinks->contains(fn ($link) => request()->routeIs($link['active']))) aria-current="page" @endif>
            {!! $tabIcon('<circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5-5 2 2-5z"/>') !!}<span>Explore</span>
        </a>
    @endif
    @auth
        <a href="{{ route('workspace') }}" @if (request()->routeIs('workspace', 'workspace.*')) aria-current="page" @endif>
            {!! $tabIcon('<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>') !!}<span>Workspace</span>
        </a>
        <a href="{{ route('notifications.index') }}" @if (request()->routeIs('notifications.*')) aria-current="page" @endif>
            <span class="app-tab-icon">{!! $tabIcon('<path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>') !!}@if ($unreadNotifications)<span class="app-tab-badge">{{ $unreadNotifications > 99 ? '99+' : $unreadNotifications }}</span>@endif</span>
            <span>Alerts</span>@if ($unreadNotifications)<span class="visually-hidden"> ({{ $unreadNotifications }} unread)</span>@endif
        </a>
    @else
        <a href="{{ route('login') }}" @if (request()->routeIs('login')) aria-current="page" @endif>
            {!! $tabIcon('<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>') !!}<span>Sign in</span>
        </a>
    @endauth
    <button type="button" class="app-tab-menu" data-toggle-target="site-nav" aria-expanded="false" aria-controls="site-nav">
        {!! $tabIcon('<path d="M4 6h16M4 12h16M4 18h16"/>') !!}<span>Menu</span>
    </button>
</nav>

<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-col footer-brand">
                <strong>{{ $brand['site_name'] }}</strong>
                @if ($brand['site_tagline'])<p>{{ $brand['site_tagline'] }}</p>@endif
                @if ($brand['footer_text'])<p>{{ $brand['footer_text'] }}</p>@endif
                @if ($socialLinks)
                    <ul class="footer-social" aria-label="Social media">
                        @foreach ($socialLinks as $network => $link)
                            <li><a href="{{ $link }}" rel="noopener me" target="_blank">{{ \App\Http\Controllers\Admin\SettingsController::SOCIAL[$network] ?? $network }}</a></li>
                        @endforeach
                    </ul>
                @endif
            </div>
            @if ($exploreLinks->isNotEmpty())
                <nav class="footer-col" aria-labelledby="footer-explore">
                    <h2 id="footer-explore">Explore</h2>
                    <ul>
                        @foreach ($exploreLinks as $link)
                            <li><a href="{{ route($link['route']) }}">{{ $link['title'] }}</a></li>
                        @endforeach
                    </ul>
                </nav>
            @endif
            @if ($infoLinks->isNotEmpty())
                <nav class="footer-col" aria-labelledby="footer-info">
                    <h2 id="footer-info">Information</h2>
                    <ul>
                        @foreach ($infoLinks as $item)
                            <li><a href="{{ $item->href() }}" @if ($item->open_in_new_tab) target="_blank" rel="noopener" @endif>{{ $item->label }}</a></li>
                        @endforeach
                    </ul>
                </nav>
            @endif
            <div class="footer-col">
                <h2>Get in touch</h2>
                @if ($brand['contact_email'] || $brand['contact_phone'] || $brand['contact_address'])
                    <address>
                        @if ($brand['contact_address'])<span>{{ $brand['contact_address'] }}</span>@endif
                        @if ($brand['contact_phone'])<a href="tel:{{ preg_replace('/[^0-9+]/', '', $brand['contact_phone']) }}">{{ $brand['contact_phone'] }}</a>@endif
                        @if ($brand['contact_email'])<a href="mailto:{{ $brand['contact_email'] }}">{{ $brand['contact_email'] }}</a>@endif
                    </address>
                @endif
                <ul>
                    @auth
                        <li><a href="{{ route('workspace') }}">My workspace</a></li>
                    @else
                        <li><a href="{{ route('login') }}">Sign in</a></li>
                    @endauth
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; {{ now()->year }} {{ $brand['site_name'] }}. All rights reserved.</p>
            <a href="#main">Back to top</a>
        </div>
    </div>
</footer>
</body>
</html>
