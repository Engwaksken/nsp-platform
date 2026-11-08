@extends('layouts.admin')
@section('title', $framework->name.' v'.$framework->version)

@section('breadcrumbs')
    <li><a href="{{ route('admin.vgi.index') }}">Growth assessments</a></li>
    <li aria-current="page">v{{ $framework->version }}</li>
@endsection

@section('content')
    @php($definition = $framework->definition)
    <div class="page-head">
        <h1>{{ $framework->name }} v{{ $framework->version }} <span class="badge badge-{{ ['approved' => 'on', 'draft' => 'draft'][$framework->status] ?? 'off' }}">{{ ucfirst($framework->status) }}</span></h1>
    </div>
    @if ($problems)
        <div class="alert alert-warning"><strong>Fix before approving:</strong><ul>@foreach ($problems as $problem)<li>{{ $problem }}</li>@endforeach</ul></div>
    @endif

    <div data-tabs="Rubric version" data-tabs-key="vgi-edit">
    <section class="panel" data-tab="Preview" aria-labelledby="preview-heading">
        <h2 id="preview-heading">Preview</h2>
        <p class="hint">How founders will see it. Scores and tips are only visible to staff here; founders see tips on their results for weak answers (score below 50).</p>
        @foreach ($definition['dimensions'] ?? [] as $dimension)
            <details class="faq">
                <summary>{{ $dimension['title'] }} · weight {{ $dimension['weight'] }} · {{ count($dimension['questions']) }} questions</summary>
                @foreach ($dimension['questions'] as $question)
                    <p><strong>{{ $question['prompt'] }}</strong> <span class="meta">({{ $question['key'] }}, weight {{ $question['weight'] ?? 1 }}, evidence {{ $question['evidence'] ?? 'none' }})</span></p>
                    <ul>
                        @foreach ($question['options'] as $option)
                            <li>{{ $option['label'] }} · <strong>{{ $option['score'] }}</strong>@if (!empty($option['tip']))<br><span class="meta">Tip: {{ $option['tip'] }}</span>@endif</li>
                        @endforeach
                    </ul>
                @endforeach
            </details>
        @endforeach
        <h3>Stages</h3>
        <ul>@foreach ($definition['bands'] ?? [] as $band)<li><strong>{{ $band['label'] }}</strong> {{ $band['min'] }}–{{ $band['max'] }}: {{ $band['description'] ?? '' }}</li>@endforeach</ul>
    </section>

    @if ($framework->status === 'draft')
        @can('edit-vgi-frameworks')
            <form method="post" action="{{ route('admin.vgi.update', $framework) }}" class="panel" data-tab="Edit">
                @csrf @method('PUT')
                <h2>Edit</h2>
                <p class="hint">The rubric is JSON. Each dimension has a weight (relative), questions (key, prompt, weight, evidence: none/optional/required) and 2–7 options (key, label, score 0–100, optional tip). Bands must cover 0–100 in steps of 0.1 (for example 0–24.9, 25–44.9 …). Founders’ scores are recalculated only from the approved version they used.</p>
                @include('partials.field', ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'rows' => 2, 'value' => $framework->description])
                <div class="form-group">
                    <label for="definition">Rubric (JSON)</label>
                    <textarea id="definition" name="definition" rows="28" spellcheck="false" class="code-input" @error('definition') aria-invalid="true" @enderror>{{ $json }}</textarea>
                    @error('definition')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div class="actions"><button type="submit" class="btn btn-primary">Save draft</button></div>
            </form>
        @endcan
        @can('approve-vgi-frameworks')
            <form method="post" action="{{ route('admin.vgi.approve', $framework) }}" class="panel" data-tab="Approve" data-confirm="Approve this version? Founders will use it for all new assessments, and it can no longer be edited.">
                @csrf
                <h2>Approve</h2>
                <p class="hint">Someone other than the last editor ({{ $framework->editor?->name ?? 'the seeded starter draft' }}) must approve. Approving replaces the current version for new assessments; existing results keep the version they used.</p>
                @include('partials.field', ['name' => 'note', 'label' => 'Approval note', 'type' => 'textarea', 'rows' => 2, 'required' => true, 'hint' => 'What was reviewed and by whom, for example: “Reviewed by the programs team on 6 Nov.”'])
                <div class="actions"><button type="submit" class="btn btn-primary">Approve version {{ $framework->version }}</button></div>
            </form>
        @endcan
    @endif
    </div>
    @if ($framework->status !== 'draft')
        <p class="hint">This version is {{ $framework->status }} and cannot be edited. To change the rubric, create a new draft version from the overview.</p>
    @endif
@endsection
