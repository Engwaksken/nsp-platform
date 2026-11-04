<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
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
    return '<li><a href="' . e(route($route)) . '"' . ($active ? ' aria-current="page"' : '') . '>' . e($label) . '</a></li>';
})
<div class="admin-shell">
    <aside class="admin-sidebar" id="admin-sidebar">
        <a class="brand" href="{{ route('admin.dashboard') }}">{{ $brand['site_name'] }} Admin</a>
        <nav aria-label="Admin">
            <ul>
                {!! $navLink('admin.dashboard', 'Dashboard', 'admin.dashboard') !!}
            </ul>
            @if ($user->canAny(['manage-pages', 'manage-posts', 'manage-media', 'manage-menus', 'manage-site-content']))
                <p class="nav-heading">Content</p>
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
                </ul>
            @endif
            @if ($user->canAny(['manage-startups', 'review-duplicates', 'edit-vgi-frameworks', 'approve-vgi-frameworks', 'review-assessments', 'manage-integrations', 'manage-opportunities', 'verify-investors', 'manage-experts', 'manage-talent']) && \App\Support\Features::enabled('registry'))
                <p class="nav-heading">Registry</p>
                <ul>
                    @can('manage-startups') {!! $navLink('admin.startups.index', 'Startups', 'admin.startups.*') !!} @endcan
                    @can('review-duplicates') {!! $navLink('admin.duplicates.index', 'Possible duplicates', 'admin.duplicates.*') !!} @endcan
                    @if (\App\Support\Features::enabled('opportunities'))@can('manage-opportunities') {!! $navLink('admin.opportunities.index', 'Opportunities', 'admin.opportunities.*') !!} @endcan @endif
                    @if (\App\Support\Features::enabled('vgi') && $user->canAny(['edit-vgi-frameworks', 'approve-vgi-frameworks', 'review-assessments'])) {!! $navLink('admin.vgi.index', 'Growth assessments', 'admin.vgi.*') !!} @endif
                    @if (\App\Support\Features::enabled('funding'))@can('verify-investors') {!! $navLink('admin.investors.index', 'Investors', 'admin.investors.*') !!} @endcan @endif
                    @if (\App\Support\Features::enabled('experts'))@can('manage-experts') {!! $navLink('admin.experts.index', 'Experts', 'admin.experts.*') !!} {!! $navLink('admin.engagements.index', 'Mentoring and engagements', 'admin.engagements.*') !!} @endcan @endif
                    @if (\App\Support\Features::enabled('talent'))@can('manage-talent') {!! $navLink('admin.vacancies.index', 'Vacancies', 'admin.vacancies.*') !!} @endcan @endif
                    @if (\App\Support\Features::enabled('ims_integration'))@can('manage-integrations') {!! $navLink('admin.integrations.index', 'Integrations', 'admin.integrations.*') !!} @endcan @endif
                </ul>
            @endif
            @if ($user->canAny(['manage-contact-messages', 'manage-redirects']))
                <p class="nav-heading">Engagement</p>
                <ul>
                    @can('manage-contact-messages') {!! $navLink('admin.messages.index', 'Contact messages', 'admin.messages.*') !!} @endcan
                    @can('manage-redirects') {!! $navLink('admin.redirects.index', 'Redirects', 'admin.redirects.*') !!} @endcan
                </ul>
            @endif
            @if ($user->canAny(['manage-users', 'manage-settings', 'manage-feature-flags', 'view-audit-log']))
                <p class="nav-heading">System</p>
                <ul>
                    @can('manage-users') {!! $navLink('admin.users.index', 'Users and roles', 'admin.users.*') !!} @endcan
                    @can('manage-settings') {!! $navLink('admin.settings.edit', 'Branding and settings', 'admin.settings.*') !!} @endcan
                    @can('manage-feature-flags') {!! $navLink('admin.features.index', 'Feature flags', 'admin.features.*') !!} @endcan
                    @can('view-audit-log') {!! $navLink('admin.audit.index', 'Audit log', 'admin.audit.*') !!} @endcan
                </ul>
            @endif
        </nav>
    </aside>
    <div class="admin-main">
        <div class="admin-topbar">
            <button type="button" class="nav-toggle btn btn-secondary btn-sm" data-toggle-target="admin-sidebar" aria-expanded="false" aria-controls="admin-sidebar">Menu</button>
            <a href="{{ route('home') }}">View site</a>
            <div class="actions">
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
