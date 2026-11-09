{{-- Sidebar for one startup's workspace. Expects $startup. Sections only list links whose feature is on. --}}
@php
    $on = fn (string $flag) => \App\Support\Features::enabled($flag);
    $sections = [
        'Startup' => [
            ['workspace.startups.overview', 'Dashboard', 'workspace.startups.overview', true],
            ['workspace.startups.show', 'Profile', 'workspace.startups.show|workspace.startups.edit', true],
            ['workspace.startups.team', 'Team', 'workspace.startups.team*', true],
            ['workspace.startups.consents', 'Data sharing', 'workspace.startups.consents*', true],
            ['workspace.startups.history', 'Change history', 'workspace.startups.history', true],
        ],
        'Grow' => [
            ['workspace.growth', 'Growth', 'workspace.growth*', $on('vgi')],
            ['workspace.intelligence', 'Guidance', 'workspace.intelligence*', $on('intelligence')],
            ['workspace.builder', 'Venture Builder', 'workspace.builder*', $on('venture_builder')],
            ['workspace.assistant', 'AI assistant', 'workspace.assistant*', $on('ai_assistant') && \App\Ai\Assistant::unavailableReason() === null],
        ],
        'Opportunities' => [
            ['workspace.opportunities', 'Opportunities', 'workspace.opportunities*', $on('opportunities')],
            ['workspace.funding', 'Funding', 'workspace.funding*', $on('funding')],
            ['workspace.experts', 'Experts', 'workspace.experts*', $on('experts')],
            ['workspace.startups.programs', 'Applications & programs', 'workspace.startups.programs', $on('ims_integration')],
        ],
        'Market' => [
            ['workspace.talent', 'Talent', 'workspace.talent*', $on('talent')],
            ['workspace.marketplace', 'Marketplace', 'workspace.marketplace*', $on('marketplace')],
            // Shown only once its page exists (the organizations module is not finished).
            ['workspace.organizations', 'Organizations', 'workspace.organizations*', $on('organizations') && view()->exists('workspace.organizations')],
        ],
    ];
    $otherStartups = \App\Models\Registry\StartupMembership::query()->with('startup')
        ->where('user_id', auth()->id())->where('status', 'active')->where('startup_uuid', '!=', $startup->uuid)
        ->whereHas('startup', fn ($q) => $q->where('status', 'active'))->get()
        ->map->startup->sortBy(fn ($s) => strtolower($s->display_name));
@endphp
<div class="workspace-identity">
    <span class="meta">Workspace</span>
    <strong>{{ $startup->display_name }}</strong>
    @if ($otherStartups->isNotEmpty())
        <details class="workspace-switcher">
            <summary>Switch startup</summary>
            <ul>
                @foreach ($otherStartups as $other)
                    <li>
                        <form method="post" action="{{ route('workspace.startups.switch', $other) }}">
                            @csrf
                            <button type="submit" class="link-button">{{ $other->display_name }}</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </details>
    @endif
</div>
<nav aria-label="{{ $startup->display_name }} workspace">
    @foreach ($sections as $heading => $links)
        @php($links = array_filter($links, fn ($link) => $link[3] && \Illuminate\Support\Facades\Route::has($link[0])))
        @if ($links)
            <p class="nav-heading">{{ $heading }}</p>
            <ul>
                @foreach ($links as [$route, $label, $active])
                    <li><a href="{{ route($route, $startup) }}" @if (request()->routeIs(...explode('|', $active))) aria-current="page" @endif>{{ $label }}</a></li>
                @endforeach
            </ul>
        @endif
    @endforeach
    <ul class="workspace-sidebar-foot">
        <li><a href="{{ route('workspace.startups.index') }}">All my startups</a></li>
    </ul>
</nav>
