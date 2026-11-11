{{--
    Investment readiness report body, shared by the founder page, the investor page and the print view.
    Needs $report (CapitalService::readiness) and $audience ("founder" or "investor").
    A section that is null was not shared with this investor and says so; nothing else is shown for it.
--}}
@php
    $scopeLabels = collect(\App\Capital\CapitalService::scopes())->map(fn ($s) => $s['label']);
    $notShared = $audience === 'investor' ? 'Not shared with you.' : 'Not available.';
    $request = $report['request'];
@endphp

<section class="panel capital-report-section" aria-labelledby="report-request">
    <h2 id="report-request"><i class="fas fa-sack-dollar" aria-hidden="true"></i> Funding request</h2>
    <dl class="detail-grid">
        <div><dt>Raising</dt><dd>{{ $request['amount'] ?? 'No open request' }}</dd></div>
        <div><dt>Round</dt><dd>{{ $report['round_stage'] ?? 'Not set' }}</dd></div>
        <div><dt>Instruments</dt><dd>{{ $request && $request['instruments'] ? implode(', ', $request['instruments']) : 'Not set' }}</dd></div>
        @if ($request && $request['pitch'])
            <div class="detail-wide"><dt>Pitch</dt><dd>{!! nl2br(e($request['pitch'])) !!}</dd></div>
        @endif
        @if ($request && $request['use_of_funds'])
            <div class="detail-wide"><dt>Use of funds</dt><dd>{!! nl2br(e($request['use_of_funds'])) !!}</dd></div>
        @endif
    </dl>
</section>

<section class="panel capital-report-section" aria-labelledby="report-vgi">
    <h2 id="report-vgi"><i class="fas fa-arrow-trend-up" aria-hidden="true"></i> {{ $scopeLabels['vgi_summary'] ?? 'Growth Index summary' }}</h2>
    @if ($report['vgi'] === null)
        <p class="empty">{{ $notShared }}</p>
    @elseif (! $report['vgi']['data'])
        <p class="empty">No growth assessment yet.</p>
    @else
        @php($vgi = $report['vgi']['data'])
        <dl class="detail-grid">
            <div><dt>Stage</dt><dd>{{ $vgi['stage'] ?? 'Not set' }}</dd></div>
            <div><dt>Overall score</dt><dd>{{ $vgi['score'] !== null ? number_format($vgi['score'], 1) : 'Not set' }}</dd></div>
            <div><dt>Rubric version</dt><dd>{{ $vgi['version'] ?? 'Not set' }} @if ($vgi['provisional'])<span class="badge badge-draft">Provisional</span>@endif</dd></div>
            <div><dt>Assessed</dt><dd>{{ $vgi['assessed_at'] ?? 'Not set' }}</dd></div>
        </dl>
        @if ($vgi['dimensions'])
            <ul class="capital-bars" aria-label="Scores by dimension">
                @foreach ($vgi['dimensions'] as $dimension)
                    <li>
                        <span class="meter-label"><span>{{ $dimension['name'] }}</span><strong>{{ $dimension['score'] !== null ? number_format($dimension['score'], 1) : 'Not scored' }}</strong></span>
                        <span class="meter" aria-hidden="true"><span style="width: {{ max(0, min(100, (int) round($dimension['score'] ?? 0))) }}%"></span></span>
                    </li>
                @endforeach
            </ul>
        @endif
        <p class="hint">The Growth Index is a discovery aid. It is not an eligibility or investment decision.</p>
    @endif
</section>

<section class="panel capital-report-section" aria-labelledby="report-builder">
    <h2 id="report-builder"><i class="fas fa-diagram-project" aria-hidden="true"></i> {{ $scopeLabels['builder_progress'] ?? 'Venture Builder progress' }}</h2>
    @if ($report['builder'] === null)
        <p class="empty">{{ $notShared }}</p>
    @elseif (! $report['builder']['data'])
        <p class="empty">No Venture Builder progress yet.</p>
    @else
        @php($builder = $report['builder']['data'])
        <div class="meter-label"><span>Steps completed</span><strong>{{ $builder['completed'] }} of {{ $builder['total'] }} ({{ $builder['percent'] }}%)</strong></div>
        <div class="meter" aria-hidden="true"><span style="width: {{ max(0, min(100, $builder['percent'])) }}%"></span></div>
        @if ($builder['modules'])
            <ul class="capital-bars" aria-label="Progress by module">
                @foreach ($builder['modules'] as $module)
                    <li><span class="meter-label"><span>{{ $module['name'] }}</span><strong>{{ $module['completed'] }} of {{ $module['total'] }}</strong></span></li>
                @endforeach
            </ul>
        @endif
    @endif
</section>

<section class="panel capital-report-section" aria-labelledby="report-traction">
    <h2 id="report-traction"><i class="fas fa-chart-line" aria-hidden="true"></i> {{ $scopeLabels['traction'] ?? 'Traction summary' }}</h2>
    @if ($report['traction'] === null)
        <p class="empty">{{ $notShared }}</p>
    @elseif (! filled($report['traction']['text']))
        <p class="empty">No traction summary written yet.</p>
    @else
        <p>{!! nl2br(e($report['traction']['text'])) !!}</p>
    @endif
</section>

<section class="panel capital-report-section" aria-labelledby="report-financials">
    <h2 id="report-financials"><i class="fas fa-coins" aria-hidden="true"></i> {{ $scopeLabels['financials'] ?? 'Financial summary' }}</h2>
    @if ($report['financials'] === null)
        <p class="empty">{{ $notShared }}</p>
    @else
        @php($fin = $report['financials'])
        @if (filled($fin['text']))<p>{!! nl2br(e($fin['text'])) !!}</p>@else<p class="empty">No financial summary written yet.</p>@endif
        <h3>Use of funds</h3>
        @if ($fin['breakdown'])
            <ul class="capital-bars" aria-label="Use of funds">
                @foreach ($fin['breakdown'] as $label => $percent)
                    <li>
                        <span class="meter-label"><span>{{ $label }}</span><strong>{{ $percent }}%</strong></span>
                        <span class="meter" aria-hidden="true"><span style="width: {{ max(0, min(100, $percent)) }}%"></span></span>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="empty">No breakdown given.</p>
        @endif
        <h3>Financial documents in the data room</h3>
        @if ($fin['documents'])
            <ul>@foreach ($fin['documents'] as $title)<li>{{ $title }}</li>@endforeach</ul>
            @if ($audience === 'investor')<p class="hint">Titles only. Opening a document needs data-room access from the founders.</p>@endif
        @else
            <p class="empty">None yet.</p>
        @endif
    @endif
</section>

<section class="panel capital-report-section" aria-labelledby="report-room">
    <h2 id="report-room"><i class="fas fa-folder-open" aria-hidden="true"></i> Data room completeness</h2>
    @if ($report['data_room'] === null)
        <p class="empty">Shown once the founders share their data room with you.</p>
    @else
        @php($room = $report['data_room'])
        <div class="meter-label"><span>{{ $room['files'] }} document(s)</span><strong>{{ $room['completeness'] }}% of the checklist</strong></div>
        <div class="meter" aria-hidden="true"><span style="width: {{ $room['completeness'] }}%"></span></div>
        <ul class="capital-checklist">
            @foreach ($room['checklist'] as $entry)
                <li><i class="fas {{ $entry['present'] ? 'fa-circle-check capital-yes' : 'fa-circle-xmark capital-no' }}" aria-hidden="true"></i> {{ $entry['label'] }}<span class="visually-hidden">: {{ $entry['present'] ? 'present' : 'missing' }}</span></li>
            @endforeach
        </ul>
    @endif
</section>

@if ($audience === 'founder' && $report['diligence'] !== null)
    <section class="panel capital-report-section" aria-labelledby="report-diligence">
        <h2 id="report-diligence"><i class="fas fa-list-check" aria-hidden="true"></i> Due diligence across investors</h2>
        @if ($report['diligence'] === [])
            <p class="empty">No checklist items yet.</p>
        @else
            <dl class="detail-grid">
                @foreach (\App\Models\Capital\DiligenceItem::statuses() as $key => $label)
                    <div><dt>{{ $label }}</dt><dd>{{ $report['diligence'][$key] ?? 0 }}</dd></div>
                @endforeach
            </dl>
        @endif
    </section>
@endif

<p class="meta">Generated {{ $report['generated_at']->format('j F Y, H:i') }}. Readiness is a discovery aid: it is not an eligibility decision, a finance approval or an investment decision.</p>
