@extends('layouts.admin')
@section('title', 'KPIs and reports')

@section('content')
    <div class="page-head">
        <h1>KPIs and reports</h1>
        <div class="inline-form">
            <a class="btn btn-secondary" href="{{ route('admin.insights.export', ['days' => $days]) }}">Download KPIs (CSV)</a>
            @can('manage-startups')<a class="btn btn-secondary" href="{{ route('admin.insights.startups') }}">Download startup list (CSV)</a>@endcan
        </div>
    </div>
    <nav class="tags" aria-label="Period">
        @foreach (\App\Http\Controllers\Admin\InsightsController::PERIODS as $value => $label)
            <a class="tag" href="{{ route('admin.insights.index', ['days' => $value]) }}" @if ($days === $value) aria-current="true" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <p class="hint">From {{ $from->format('j M Y') }} to today. Counts only: no names or contact details. Definitions are in <code>docs/kpi-definitions.md</code>. Downloads are recorded in the audit log.</p>

    @foreach ($kpis as $group => $items)
        <h2>{{ $group }}</h2>
        <div class="grid">
            @foreach ($items as $key => $kpi)
                <section class="card" aria-labelledby="kpi-{{ $key }}">
                    <h3 id="kpi-{{ $key }}" class="h4">{{ $kpi['label'] }}</h3>
                    <p class="kpi-value">{{ is_numeric($kpi['value']) ? number_format($kpi['value']) : $kpi['value'] }}</p>
                    <p class="meta">{{ $kpi['detail'] }}</p>
                </section>
            @endforeach
        </div>
    @endforeach

    <h2>New accounts and startups by month</h2>
    <div class="table-wrap" tabindex="0" role="region" aria-label="New accounts and startups by month">
        <table>
            <thead><tr><th scope="col">Month</th><th scope="col">New accounts</th><th scope="col">New startups</th></tr></thead>
            <tbody>
            @foreach ($monthly as $row)
                <tr><th scope="row">{{ $row['month'] }}</th><td>{{ number_format($row['users']) }}</td><td>{{ number_format($row['startups']) }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endsection
