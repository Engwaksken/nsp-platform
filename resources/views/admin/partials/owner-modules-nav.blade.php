{{-- Admin navigation for owner-alignment modules. Each link appears once its module's routes exist. --}}
@php($ownerLinks = array_filter([
    ['admin.organizations.index', 'Organizations', 'admin.organizations.*', 'manage-organizations', 'organizations'],
    ['admin.builder.index', 'Venture Builder curricula', 'admin.builder.*', ['manage-builder-curricula', 'publish-builder-curricula', 'review-builder-steps'], 'venture_builder'],
    ['admin.intelligence.index', 'Guidance rules and templates', 'admin.intelligence.*', 'manage-intelligence', 'intelligence'],
    ['admin.challenges.index', 'Challenge Exchange', 'admin.challenges.*', ['manage-challenges', 'review-challenges'], 'challenges'],
    ['admin.knowledge.index', 'Knowledge Hub', 'admin.knowledge.*', ['manage-knowledge', 'publish-knowledge'], 'knowledge'],
    ['admin.payments.index', 'Payments ledger (sandbox)', 'admin.payments.*', 'manage-payments', 'experts'],
], fn ($l) => \Illuminate\Support\Facades\Route::has($l[0]) && \App\Support\Features::enabled($l[4]) && $user->canAny((array) $l[3])))
@if ($ownerLinks)
    <p class="nav-heading">Venture building</p>
    <ul>
        @foreach ($ownerLinks as $l) {!! $navLink($l[0], $l[1], $l[2]) !!} @endforeach
    </ul>
@endif
