<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Readiness report · {{ $startup->display_name }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/fontawesome.min.css') }}?v=6.5.2">
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/solid.min.css') }}?v=6.5.2">
    <link rel="stylesheet" href="{{ asset('css/nsp.css') }}?v=28">
    <style>
        body { background: #fff; }
        .report { max-width: 820px; margin: 0 auto; padding: 24px 16px; }
        @media print { .no-print { display: none !important; } .report { padding: 0; } a { color: inherit; text-decoration: none; } }
    </style>
</head>
<body>
<main class="report">
    <p class="no-print"><a href="{{ $backUrl }}"><i class="fas fa-arrow-left" aria-hidden="true"></i> Back to the readiness report</a> · Use your browser’s Print (Ctrl+P) to save as PDF.</p>
    <h1>Investment readiness: {{ $startup->display_name }}</h1>
    <p class="meta">{{ $startup->legal_name }}@if ($startup->profile?->sector) · {{ $startup->profile->sector }}@endif @if ($startup->profile?->headquarters_location) · {{ $startup->profile->headquarters_location }}@endif</p>
    @if ($audience === 'investor')
        <p class="meta">Confidential. Shared with you by the founders through NSP. Do not pass it on.</p>
    @endif

    @include('capital.report')
</main>
</body>
</html>
