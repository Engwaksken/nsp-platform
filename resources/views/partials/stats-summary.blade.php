{{--
    One compact line of counts above a public listing (instead of dashboard-style stat cards).
    @include('partials.stats-summary', ['label' => 'Jobs at a glance', 'stats' => \App\Support\PublicStats::jobs()])

    The first row is the headline total; the others are shown only when above zero, and a row with
    'query' becomes a link to this listing filtered that way. Nothing is shown when the total is zero
    (the listing's own empty state explains that).
--}}
@php
    $rows = array_values($stats ?? []);
    $total = $rows[0] ?? null;
    $rest = array_filter(array_slice($rows, 1), fn ($row) => (int) ($row['value'] ?? 0) > 0);
    $lower = fn (string $text) => \Illuminate\Support\Str::lcfirst($text);
@endphp
@if ($total && (int) $total['value'] > 0)
    <p class="stats-summary" aria-label="{{ $label ?? 'At a glance' }}">
        <span class="stats-summary-total"><strong>{{ number_format((int) $total['value']) }}</strong> {{ $lower($total['label']) }}</span>
        @foreach ($rest as $row)
            @php($url = isset($row['query']) ? url()->current().'?'.http_build_query($row['query']) : null)
            @php($current = isset($row['query']) && collect($row['query'])->every(fn ($value, $key) => request()->query($key) === (string) $value))
            @if ($url)
                <a class="stats-chip" href="{{ $url }}" @if ($current) aria-current="true" @endif><strong>{{ number_format((int) $row['value']) }}</strong> {{ $lower($row['label']) }}</a>
            @else
                <span class="stats-chip"><strong>{{ number_format((int) $row['value']) }}</strong> {{ $lower($row['label']) }}</span>
            @endif
        @endforeach
    </p>
@endif
