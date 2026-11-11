@extends('layouts.admin')
@section('title', 'Assessment · '.$assessment->startup?->display_name)

@section('breadcrumbs')
    <li><a href="{{ route('admin.vgi.index') }}">Growth assessments</a></li>
    <li aria-current="page">{{ $assessment->startup?->display_name }}</li>
@endsection

@section('content')
    <div class="page-head"><h1>{{ $assessment->startup?->display_name }}: assessment of {{ $assessment->submitted_at->format('j M Y') }}</h1></div>
    <p class="hint">Viewing this page is recorded in the audit log.
        Recalculating from the stored answers and rubric: <strong>{{ $reproducible ? 'matches the saved result' : 'DOES NOT MATCH, investigate' }}</strong>.</p>

    @include('workspace.growth.scores')

    <form method="post" action="{{ route('admin.vgi.override', $assessment) }}" class="panel">
        @csrf
        <h2>Adjust the result</h2>
        <p class="hint">Only adjust with a clear reason (for example after verifying evidence). The calculated result is never deleted, and the startup sees your reason.</p>
        <div class="form-row">
            @include('partials.field', ['name' => 'override_score', 'label' => 'Adjusted score (optional)', 'type' => 'number', 'value' => $assessment->override_score, 'attrs' => 'min="0" max="100" step="0.1"'])
            @include('partials.field', ['name' => 'override_band', 'label' => 'Adjusted stage (optional)', 'type' => 'select', 'placeholder' => 'Keep calculated stage',
                'options' => collect($assessment->framework->definition['bands'])->mapWithKeys(fn ($b) => [str_replace(',', '', $b['label']) => $b['label']])->all(), 'value' => $assessment->override_band_label])
        </div>
        @include('partials.field', ['name' => 'reason', 'label' => 'Reason', 'type' => 'textarea', 'rows' => 2, 'required' => true, 'value' => $assessment->override_reason])
        <button type="submit" class="btn btn-primary">Save adjustment</button>
    </form>

    <details class="panel">
        <summary>Answers and evidence</summary>
        @php($responses = $assessment->responses->keyBy('question_key'))
        @foreach ($assessment->framework->definition['dimensions'] as $dimension)
            <h3>{{ $dimension['title'] }}</h3>
            <ul>
                @foreach ($dimension['questions'] as $question)
                    @php($response = $responses->get($question['key']))
                    @php($option = collect($question['options'])->firstWhere('key', $response?->option_key))
                    <li>{{ $question['prompt'] }} <strong>{{ $option['label'] ?? 'No answer' }}</strong> ({{ $option['score'] ?? 'no score' }})
                        @if ($response?->evidence_note)<br><span class="meta">Evidence: {{ $response->evidence_note }}</span>@endif</li>
                @endforeach
            </ul>
        @endforeach
    </details>
@endsection
