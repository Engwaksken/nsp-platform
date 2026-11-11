{{-- Score summary for a submitted assessment. Expects $assessment. --}}
@php($result = $assessment->result)
<div class="kpis">
    <div class="kpi"><strong>{{ number_format($assessment->displayScore(), 1) }}</strong>Growth score out of 100</div>
    <div class="kpi"><strong>{{ $assessment->displayBand() }}</strong>Stage</div>
    <div class="kpi"><strong>{{ $assessment->submitted_at->format('j M Y') }}</strong>Assessed</div>
</div>
@if ($assessment->override_at)
    <div class="alert alert-info" role="note">
        Hive staff adjusted this result on {{ $assessment->override_at->format('j M Y') }}: “{{ $assessment->override_reason }}”
        The calculated result from your answers was {{ number_format($assessment->score, 1) }} ({{ $assessment->band_label }}).
    </div>
@endif
<p class="hint">This is a self-assessment based on your team’s own answers to {{ $assessment->framework->name }} version {{ $assessment->framework->version }}. It is not a verified rating.</p>
<table>
    <caption class="visually-hidden">Scores by area</caption>
    <thead><tr><th scope="col">Area</th><th scope="col">Score</th><th scope="col"><span class="visually-hidden">Bar</span></th></tr></thead>
    <tbody>
    @foreach ($result['dimensions'] as $dimension)
        <tr>
            <th scope="row">{{ $dimension['title'] }}</th>
            @if ($dimension['score'] === null)
                <td colspan="2"><span class="meta">Insufficient information: answer more questions in this area to get a score.</span></td>
            @else
                <td>{{ number_format($dimension['score'], 1) }}</td>
                <td style="width:50%"><meter min="0" max="100" low="45" high="70" optimum="100" value="{{ $dimension['score'] }}" aria-label="{{ $dimension['title'] }}: {{ $dimension['score'] }} out of 100" style="width:100%"></meter></td>
            @endif
        </tr>
    @endforeach
    </tbody>
</table>
