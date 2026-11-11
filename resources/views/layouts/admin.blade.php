<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="no-js">
<head>
    @php($pageTitle = trim($__env->yieldContent('title')) . ' · Admin')
    @include('partials.head')
    <meta name="robots" content="noindex, nofollow">
</head>
<body>
<a class="skip-link" href="#main">Skip to main content</a>
@php($user = auth()->user())
@php($navLink = function (string $route, string $label, string $pattern) {
    $active = request()->routeIs($pattern);
    $icons = [
        'admin.dashboard' => 'fa-gauge-high', 'admin.pages.index' => 'fa-file-lines', 'admin.posts.index' => 'fa-newspaper',
        'admin.categories.index' => 'fa-folder-open', 'admin.media.index' => 'fa-photo-film', 'admin.menus.index' => 'fa-bars',
        'admin.insights.index' => 'fa-chart-line', 'admin.startups.index' => 'fa-rocket', 'admin.duplicates.index' => 'fa-code-compare',
        'admin.opportunities.index' => 'fa-bullhorn', 'admin.vgi.index' => 'fa-chart-pie', 'admin.investors.index' => 'fa-building-columns',
        'admin.experts.index' => 'fa-user-tie', 'admin.engagements.index' => 'fa-handshake', 'admin.vacancies.index' => 'fa-briefcase',
        'admin.marketplace.index' => 'fa-store', 'admin.integrations.index' => 'fa-plug', 'admin.communications.index' => 'fa-paper-plane',
        'admin.messages.index' => 'fa-envelope', 'admin.redirects.index' => 'fa-arrow-right-arrow-left', 'admin.users.index' => 'fa-users',
        'admin.settings.edit' => 'fa-sliders', 'admin.features.index' => 'fa-toggle-on', 'admin.ai.edit' => 'fa-wand-magic-sparkles',
        'admin.audit.index' => 'fa-clipboard-list', 'admin.system' => 'fa-heart-pulse',
        'admin.organizations.index' => 'fa-building', 'admin.builder.index' => 'fa-diagram-project',
        'admin.intelligence.index' => 'fa-brain', 'admin.challenges.index' => 'fa-lightbulb',
        'admin.knowledge.index' => 'fa-book-open', 'admin.payments.index' => 'fa-credit-card',
    ];
    $icon = $icons[$route] ?? 'fa-circle';
    return '<li><a href="' . e(route($route)) . '" title="' . e($label) . '"' . ($active ? ' aria-current="page"' : '') . '><i class="fas ' . $icon . ' nav-item-icon" aria-hidden="true"></i><span>' . e($label) . '</span></a></li>';
})
<div class="admin-shell">
    <aside class="admin-sidebar" id="admin-sidebar" data-offcanvas>
        <div class="app-sidebar-head">
            <a class="brand" href="{{ route('admin.dashboard') }}">{{ $brand['site_name'] }} Admin</a>
            {{-- Without JavaScript on small screens the menu opens in place via #admin-sidebar (see "no-js" in nsp.css). --}}
            <a class="app-sidebar-close nojs-only" href="#main" aria-label="Close menu"><i class="fas fa-xmark" aria-hidden="true"></i></a>
        </div>
        <nav aria-label="Admin">
            <ul>
                {!! $navLink('admin.dashboard', 'Dashboard', 'admin.dashboard') !!}
            </ul>
            @if ($user->canAny(['manage-pages', 'manage-posts', 'manage-media', 'manage-menus', 'manage-site-content']))
                <details class="nav-group" @if (request()->routeIs('admin.pages.*', 'admin.posts.*', 'admin.categories.*', 'admin.media.*', 'admin.menus.*', 'admin.content.*')) open @endif>
                <summary class="nav-heading"><i class="fas fa-pen-to-square" aria-hidden="true"></i><span>Content</span></summary>
                <ul>
                    @can('manage-pages') {!! $navLink('admin.pages.index', 'Pages', 'admin.pages.*') !!} @endcan
                    @can('manage-posts')
                        {!! $navLink('admin.posts.index', 'News', 'admin.posts.*') !!}
                        {!! $navLink('admin.categories.index', 'Categories', 'admin.categories.*') !!}
                    @endcan
                    @can('manage-media') {!! $navLink('admin.media.index', 'Media library', 'admin.media.*') !!} @endcan
                    @can('manage-menus') {!! $navLink('admin.menus.index', 'Menus', 'admin.menus.*') !!} @endcan
                    @can('manage-site-content')
                        <li><a href="{{ route('admin.content.index', 'faqs') }}" @if (request()->is('admin/content/faqs*')) aria-current="page" @endif>FAQs</a></li>
                        <li><a href="{{ route('admin.content.index', 'testimonials') }}" @if (request()->is('admin/content/testimonials*')) aria-current="page" @endif>Testimonials</a></li>
                        <li><a href="{{ route('admin.content.index', 'partners') }}" @if (request()->is('admin/content/partners*')) aria-current="page" @endif>Partners</a></li>
                    @endcan
                </ul></details>
            @endif
            @can('view-insights')
                <details class="nav-group" @if (request()->routeIs('admin.insights.*')) open @endif><summary class="nav-heading"><i class="fas fa-chart-line" aria-hidden="true"></i><span>Insights</span></summary>
                <ul>{!! $navLink('admin.insights.index', 'KPIs and reports', 'admin.insights.*') !!}</ul></details>
            @endcan
            @if ($user->canAny(['manage-startups', 'review-duplicates', 'edit-vgi-frameworks', 'approve-vgi-frameworks', 'review-assessments', 'manage-integrations', 'manage-opportunities', 'verify-investors', 'manage-experts', 'manage-talent', 'moderate-marketplace']) && \App\Support\Features::enabled('registry'))
                <details class="nav-group" @if (request()->routeIs('admin.startups.*', 'admin.duplicates.*', 'admin.opportunities.*', 'admin.vgi.*', 'admin.investors.*', 'admin.experts.*', 'admin.engagements.*', 'admin.vacancies.*', 'admin.marketplace.*', 'admin.integrations.*')) open @endif><summary class="nav-heading"><i class="fas fa-database" aria-hidden="true"></i><span>Registry</span></summary>
                <ul>
                    @can('manage-startups') {!! $navLink('admin.startups.index', 'Startups', 'admin.startups.*') !!} @endcan
                    @can('review-duplicates') {!! $navLink('admin.duplicates.index', 'Possible duplicates', 'admin.duplicates.*') !!} @endcan
                    @if (\App\Support\Features::enabled('opportunities'))@can('manage-opportunities') {!! $navLink('admin.opportunities.index', 'Opportunities', 'admin.opportunities.*') !!} @endcan @endif
                    @if (\App\Support\Features::enabled('vgi') && $user->canAny(['edit-vgi-frameworks', 'approve-vgi-frameworks', 'review-assessments'])) {!! $navLink('admin.vgi.index', 'Growth assessments', 'admin.vgi.*') !!} @endif
                    @if (\App\Support\Features::enabled('funding'))@can('verify-investors') {!! $navLink('admin.investors.index', 'Investors', 'admin.investors.*') !!} @endcan @endif
                    @if (\App\Support\Features::enabled('experts'))@can('manage-experts') {!! $navLink('admin.experts.index', 'Experts', 'admin.experts.*') !!} {!! $navLink('admin.engagements.index', 'Mentoring and engagements', 'admin.engagements.*') !!} @endcan @endif
                    @if (\App\Support\Features::enabled('talent'))@can('manage-talent') {!! $navLink('admin.vacancies.index', 'Vacancies', 'admin.vacancies.*') !!} @endcan @endif
                    @if (\App\Support\Features::enabled('marketplace'))@can('moderate-marketplace') {!! $navLink('admin.marketplace.index', 'Marketplace and disputes', 'admin.marketplace.*') !!} @endcan @endif
                    @if (\App\Support\Features::enabled('ims_integration'))@can('manage-integrations') {!! $navLink('admin.integrations.index', 'Integrations', 'admin.integrations.*') !!} @endcan @endif
                </ul></details>
            @endif
            @includeIf('admin.partials.owner-modules-nav')
            @if ($user->canAny(['manage-contact-messages', 'manage-redirects', 'manage-communications']))
                <details class="nav-group" @if (request()->routeIs('admin.communications.*', 'admin.messages.*', 'admin.redirects.*')) open @endif><summary class="nav-heading"><i class="fas fa-comments" aria-hidden="true"></i><span>Engagement</span></summary>
                <ul>
                    @can('manage-communications') {!! $navLink('admin.communications.index', 'Announcements and email log', 'admin.communications.*') !!} @endcan
                    @can('manage-contact-messages') {!! $navLink('admin.messages.index', 'Contact messages', 'admin.messages.*') !!} @endcan
                    @can('manage-redirects') {!! $navLink('admin.redirects.index', 'Redirects', 'admin.redirects.*') !!} @endcan
                </ul></details>
            @endif
            @if ($user->canAny(['manage-users', 'manage-settings', 'manage-feature-flags', 'view-audit-log', 'view-system-health']))
                <details class="nav-group" @if (request()->routeIs('admin.users.*', 'admin.settings.*', 'admin.features.*', 'admin.ai.*', 'admin.audit.*', 'admin.system')) open @endif><summary class="nav-heading"><i class="fas fa-gear" aria-hidden="true"></i><span>System</span></summary>
                <ul>
                    @can('manage-users') {!! $navLink('admin.users.index', 'Users and roles', 'admin.users.*') !!} @endcan
                    @can('manage-settings') {!! $navLink('admin.settings.edit', 'Branding and settings', 'admin.settings.*') !!} @endcan
                    @can('manage-feature-flags') {!! $navLink('admin.features.index', 'Feature flags', 'admin.features.*') !!} @endcan
                    @can('manage-feature-flags') {!! $navLink('admin.ai.edit', 'AI assistant', 'admin.ai.*') !!} @endcan
                    @can('view-audit-log') {!! $navLink('admin.audit.index', 'Audit log', 'admin.audit.*') !!} @endcan
                    @can('view-system-health') {!! $navLink('admin.system', 'System health', 'admin.system') !!} @endcan
                </ul></details>
            @endif
        </nav>
    </aside>
    <div class="shell-backdrop" data-offcanvas-close hidden></div>
    <div class="admin-main">
        <div class="admin-topbar">
            <button type="button" class="nav-toggle btn btn-secondary btn-sm" data-toggle-target="admin-sidebar" aria-expanded="false" aria-controls="admin-sidebar">Menu</button>
            <a class="nav-toggle-nojs nojs-only btn btn-secondary btn-sm" href="#admin-sidebar">Menu</a>
            <a href="{{ route('home') }}">View site</a>
            <div class="actions">
                @include('partials.theme-switch')
                <a href="{{ route('mfa.setup') }}">{{ $user->name }}<span class="visually-hidden">: account security</span></a>
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-sm">Sign out</button>
                </form>
            </div>
        </div>
        <main id="main" class="admin-content" tabindex="-1">
            @hasSection('breadcrumbs')
                <nav class="breadcrumbs" aria-label="Breadcrumb"><ol>@yield('breadcrumbs')</ol></nav>
            @endif
            @include('partials.flash')
            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
