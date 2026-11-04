<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
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
        <nav class="site-nav" id="site-nav" aria-label="Main">
            <ul>
                @foreach ($headerMenu?->items->whereNull('parent_id') ?? [] as $item)
                    <li>
                        <a href="{{ $item->href() }}" @if (url()->current() === $item->href()) aria-current="page" @endif
                           @if ($item->open_in_new_tab) target="_blank" rel="noopener" @endif>{{ $item->label }}</a>
                    </li>
                @endforeach
                @if (\App\Support\Features::enabled('opportunities'))
                    <li><a href="{{ route('opportunities.index') }}" @if (request()->routeIs('opportunities.*')) aria-current="page" @endif>Opportunities</a></li>
                @endif
                @if (\App\Support\Features::enabled('marketplace'))
                    <li><a href="{{ route('directory.index') }}" @if (request()->routeIs('directory.*')) aria-current="page" @endif>Startups</a></li>
                    <li><a href="{{ route('marketplace.index') }}" @if (request()->routeIs('marketplace.*')) aria-current="page" @endif>Marketplace</a></li>
                @endif
                @if (\App\Support\Features::enabled('talent'))
                    <li><a href="{{ route('jobs.index') }}" @if (request()->routeIs('jobs.*')) aria-current="page" @endif>Jobs</a></li>
                @endif
                @auth
                    <li><a href="{{ route('workspace') }}">My workspace</a></li>
                    @php($unreadNotifications = \App\Models\Communication\UserNotification::query()->where('user_id', auth()->id())->whereNull('read_at')->count())
                    <li><a href="{{ route('notifications.index') }}" @if (request()->routeIs('notifications.*')) aria-current="page" @endif>Notifications @if ($unreadNotifications)<span class="badge badge-on">{{ $unreadNotifications }}<span class="visually-hidden"> unread</span></span>@endif</a></li>
                    <li><a href="{{ route('mfa.setup') }}">Security</a></li>
                    @if (auth()->user()->canUseAdmin())
                        <li><a href="{{ route('admin.dashboard') }}">Admin</a></li>
                    @endif
                    <li>
                        <form method="post" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn btn-secondary btn-sm">Sign out</button>
                        </form>
                    </li>
                @else
                    <li><a class="btn btn-primary btn-sm" href="{{ route('login') }}">Sign in</a></li>
                @endauth
            </ul>
        </nav>
    </div>
</header>

<main id="main" tabindex="-1">
    @yield('content')
</main>

<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div>
                <strong>{{ $brand['site_name'] }}</strong>
                @if ($brand['site_tagline'])<p>{{ $brand['site_tagline'] }}</p>@endif
                @if ($brand['footer_text'])<p>{{ $brand['footer_text'] }}</p>@endif
            </div>
            <div>
                @if ($brand['contact_email'] || $brand['contact_phone'] || $brand['contact_address'])
                    <h2 class="visually-hidden">Contact</h2>
                    <address>
                        @if ($brand['contact_address']){{ $brand['contact_address'] }}<br>@endif
                        @if ($brand['contact_phone'])<a href="tel:{{ preg_replace('/[^0-9+]/', '', $brand['contact_phone']) }}">{{ $brand['contact_phone'] }}</a><br>@endif
                        @if ($brand['contact_email'])<a href="mailto:{{ $brand['contact_email'] }}">{{ $brand['contact_email'] }}</a>@endif
                    </address>
                @endif
            </div>
            <nav aria-label="Footer">
                <ul>
                    @foreach ($footerMenu?->items ?? [] as $item)
                        <li><a href="{{ $item->href() }}" @if ($item->open_in_new_tab) target="_blank" rel="noopener" @endif>{{ $item->label }}</a></li>
                    @endforeach
                    @foreach (\App\Support\Settings::json('social_links') as $network => $link)
                        <li><a href="{{ $link }}" rel="noopener me" target="_blank">{{ \App\Http\Controllers\Admin\SettingsController::SOCIAL[$network] ?? $network }}</a></li>
                    @endforeach
                </ul>
            </nav>
        </div>
        <p class="meta">&copy; {{ now()->year }} {{ $brand['site_name'] }}</p>
    </div>
</footer>
</body>
</html>
