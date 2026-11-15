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
    @include('partials.stats', ['label' => 'Key figures', 'stats' => $dashStats, 'mobileLimit' => 6])

    @php
        $contentChart = collect(['Published pages', 'Draft pages', 'Published news'])
            ->map(fn ($label) => ['label' => $label, 'value' => $counts[$label] ?? 0]);
        $attentionChart = collect($dashStats)->filter(fn ($stat) => in_array($stat['label'], [
            'New messages', 'Opportunities to review', 'Investors to verify', 'Experts to verify',
            'Open marketplace reports', 'Failed emails (7 days)',
        ], true))->map(fn ($stat) => ['label' => $stat['label'], 'value' => $stat['value']]);
        $chartMax = max(1, (int) $contentChart->max('value'), (int) $attentionChart->max('value'));
    @endphp
    <div class="dashboard-charts">
        <section class="panel dashboard-chart" aria-labelledby="content-chart-heading">
            <h2 id="content-chart-heading">Content overview</h2>
            <ul class="chart-bars" role="img" aria-label="Content counts: {{ $contentChart->map(fn ($row) => $row['label'].', '.$row['value'])->implode('; ') }}">
                @foreach ($contentChart as $row)
                    <li><span>{{ $row['label'] }}</span><span class="chart-track"><span class="chart-bar" style="width: {{ min(100, (int) round($row['value'] / $chartMax * 100)) }}%"></span></span><strong>{{ number_format($row['value']) }}</strong></li>
                @endforeach
            </ul>
        </section>
        <section class="panel dashboard-chart" aria-labelledby="attention-chart-heading">
            <h2 id="attention-chart-heading">Needs attention</h2>
            @if ($attentionChart->isNotEmpty())
                <ul class="chart-bars" role="img" aria-label="Items needing attention: {{ $attentionChart->map(fn ($row) => $row['label'].', '.$row['value'])->implode('; ') }}">
                    @foreach ($attentionChart as $row)
                        <li><span>{{ $row['label'] }}</span><span class="chart-track"><span class="chart-bar chart-bar-warm" style="width: {{ min(100, (int) round($row['value'] / $chartMax * 100)) }}%"></span></span><strong>{{ number_format($row['value']) }}</strong></li>
                    @endforeach
                </ul>
            @else
                <p class="hint">No attention metrics are available for your account.</p>
            @endif
        </section>
    </div>
@endsection
