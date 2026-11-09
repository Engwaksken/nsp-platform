@extends('layouts.admin')
@section('title', 'Dashboard')

@section('content')
    <div class="page-head"><h1>Dashboard</h1></div>

    @php
        $look = [
            'Published pages' => ['fa-file-lines', 'blue', 'admin.pages.index'], 'Draft pages' => ['fa-file-pen', 'grey', 'admin.pages.index'],
            'Published news' => ['fa-newspaper', 'purple', 'admin.posts.index'], 'New messages' => ['fa-envelope', 'red', 'admin.messages.index'],
            'Active users' => ['fa-users', 'blue', 'admin.users.index'], 'Active startups' => ['fa-rocket', 'orange', 'admin.startups.index'],
            'Opportunities to review' => ['fa-bullhorn', 'amber', 'admin.opportunities.index'], 'Investors to verify' => ['fa-building-columns', 'green', 'admin.investors.index'],
            'Experts to verify' => ['fa-user-tie', 'green', 'admin.experts.index'], 'Open marketplace reports' => ['fa-flag', 'red', 'admin.marketplace.index'],
            'Failed emails (7 days)' => ['fa-triangle-exclamation', 'red', 'admin.communications.index'],
        ];
        $dashStats = collect($counts)->reject(fn ($value) => $value === null)->map(fn ($value, $label) => [
            'icon' => $look[$label][0] ?? 'fa-chart-simple', 'tone' => $look[$label][1] ?? 'grey', 'label' => $label, 'value' => $value,
            'url' => isset($look[$label][2]) && \Illuminate\Support\Facades\Route::has($look[$label][2]) ? route($look[$label][2]) : null,
        ])->values()->all();
    @endphp
    @include('partials.stats', ['label' => 'Key figures', 'stats' => $dashStats])

    <div data-tabs="Dashboard sections" data-tabs-key="admin-dashboard">
        <section class="panel" aria-labelledby="features-heading" data-tab="Modules" data-tab-icon="fa-puzzle-piece" data-tab-count="{{ $features->where('enabled', true)->count() }}/{{ $features->count() }}">
            <h2 id="features-heading">Modules</h2>
            <p class="hint">Modules for later phases stay off until they are built and approved.</p>
            <ul>
                @foreach ($features as $feature)
                    <li>{{ $feature['label'] }}: <span class="badge badge-{{ $feature['enabled'] ? 'on' : 'off' }}">{{ $feature['enabled'] ? 'On' : 'Off' }}</span></li>
                @endforeach
            </ul>
        </section>

        @if ($recentActivity->isNotEmpty())
            <section class="panel" aria-labelledby="activity-heading" data-tab="Recent activity" data-tab-icon="fa-clock-rotate-left">
                <h2 id="activity-heading">Recent activity</h2>
                <ul>
                    @foreach ($recentActivity as $log)
                        <li><span class="meta">{{ $log->created_at->diffForHumans() }}</span> · {{ $log->actor?->name ?? 'System' }} · {{ $log->action }}</li>
                    @endforeach
                </ul>
                <a href="{{ route('admin.audit.index') }}">Full audit log</a>
            </section>
        @endif
    </div>
@endsection
