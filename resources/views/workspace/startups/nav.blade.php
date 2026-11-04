{{-- Tabs for one startup. Expects $startup. --}}
<nav class="tags" aria-label="{{ $startup->display_name }} sections" style="margin:1rem 0">
    @foreach ([
        'workspace.startups.overview' => 'Overview',
        'workspace.startups.show' => 'Profile',
        'workspace.startups.team' => 'Team',
        'workspace.startups.consents' => 'Data sharing',
        'workspace.startups.history' => 'Change history',
    ] + (\App\Support\Features::enabled('vgi') ? ['workspace.growth' => 'Growth'] : [])
      + (\App\Support\Features::enabled('opportunities') ? ['workspace.opportunities' => 'Opportunities'] : [])
      + (\App\Support\Features::enabled('funding') ? ['workspace.funding' => 'Funding'] : [])
      + (\App\Support\Features::enabled('experts') ? ['workspace.experts' => 'Experts'] : [])
      + (\App\Support\Features::enabled('talent') ? ['workspace.talent' => 'Talent'] : [])
      + (\App\Support\Features::enabled('marketplace') ? ['workspace.marketplace' => 'Marketplace'] : [])
      + (\App\Support\Features::enabled('ai_assistant') && \App\Ai\Assistant::unavailableReason() === null ? ['workspace.assistant' => 'AI assistant'] : [])
      + (\App\Support\Features::enabled('ims_integration') ? ['workspace.startups.programs' => 'Applications & programs'] : []) as $route => $label)
        <a class="tag" href="{{ route($route, $startup) }}" @if (request()->routeIs($route)) aria-current="true" @endif>{{ $label }}</a>
    @endforeach
    <a class="tag" href="{{ route('workspace.startups.index') }}">All my startups</a>
</nav>
