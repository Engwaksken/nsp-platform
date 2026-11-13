<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Growth report · {{ $startup->display_name }}</title>
    <link rel="stylesheet" href="{{ asset('css/nsp.css') }}?v=32">
    <style>
        body { background: #fff; }
        .report { max-width: 820px; margin: 0 auto; padding: 24px 16px; }
        @media print { .no-print { display: none !important; } .report { padding: 0; } a { color: inherit; text-decoration: none; } }
    </style>
</head>
<body>
<main class="report">
    <p class="no-print"><a href="{{ route('workspace.growth', $startup) }}"><i class="fas fa-arrow-left" aria-hidden="true"></i> Back to Growth</a> · Use your browser’s Print (Ctrl+P) to save as PDF.</p>
    <h1>Growth report: {{ $startup->display_name }}</h1>
    <p class="meta">{{ $startup->legal_name }} · {{ $startup->profile?->sector }} · {{ $startup->profile?->headquarters_location }}<br>
        Assessed {{ $assessment->submitted_at->format('j F Y') }} by {{ $assessment->submitter?->name ?? 'the team' }} · {{ $assessment->framework->name }} v{{ $assessment->framework->version }}</p>

    @include('workspace.growth.scores')

    <h2>Priorities</h2>
    @if (empty($assessment->result['gaps']))
        <p>No weak areas in this assessment.</p>
    @else
        <ol>
            @foreach (array_slice($assessment->result['gaps'], 0, 8) as $gap)
                <li><strong>{{ $gap['tip'] ?: 'Improve: '.$gap['prompt'] }}</strong> <span class="meta">({{ $gap['dimension_title'] }}, score {{ $gap['score'] }})</span></li>
            @endforeach
        </ol>
    @endif

    <h2>Open growth-plan actions</h2>
    @if ($actions->isEmpty())
        <p>None.</p>
    @else
        <table>
            <thead><tr><th scope="col">Action</th><th scope="col">Owner</th><th scope="col">Due</th><th scope="col">Status</th></tr></thead>
            <tbody>
            @foreach ($actions as $action)
                <tr><td>{{ $action->title }}</td><td>@if ($action->owner){{ $action->owner->name }}@else<span class="meta">Unassigned</span>@endif</td><td>@if ($action->due_date){{ $action->due_date->format('j M Y') }}@else<span class="meta">No due date</span>@endif</td><td>{{ \App\Models\Growth\GrowthAction::STATUSES[$action->status] }}</td></tr>
            @endforeach
            </tbody>
        </table>
    @endif
    <p class="meta" style="margin-top:2rem">Self-assessment, not a verified rating. Generated {{ now()->format('j M Y H:i') }} from Hive NSP.</p>
</main>
</body>
</html>
