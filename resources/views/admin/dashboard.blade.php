@extends('layouts.admin')
@section('title', 'Dashboard')

@section('content')
    <div class="page-head"><h1>Dashboard</h1></div>

    <div class="kpis">
        @foreach ($counts as $label => $value)
            @continue($value === null)
            <div class="kpi"><strong>{{ number_format($value) }}</strong>{{ $label }}</div>
        @endforeach
    </div>

    <div class="form-row">
        <section class="panel" aria-labelledby="features-heading">
            <h2 id="features-heading">Modules</h2>
            <p class="hint">Modules for later phases stay off until they are built and approved.</p>
            <ul>
                @foreach ($features as $feature)
                    <li>{{ $feature['label'] }}: <span class="badge badge-{{ $feature['enabled'] ? 'on' : 'off' }}">{{ $feature['enabled'] ? 'On' : 'Off' }}</span></li>
                @endforeach
            </ul>
        </section>

        @if ($recentActivity->isNotEmpty())
            <section class="panel" aria-labelledby="activity-heading">
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
