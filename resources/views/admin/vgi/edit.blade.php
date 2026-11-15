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
    @include('partials.admin-ai-panel', ['context' => 'growth', 'subjectId' => $framework->id])
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
                            <li>{{ $option['label'] }} · @if (!empty($option['insufficient']) || ! isset($option['score']))<span class="badge badge-draft">No score: not enough information</span>@else<strong>{{ $option['score'] }}</strong>@endif @if (!empty($option['tip']))<br><span class="meta">Tip: {{ $option['tip'] }}</span>@endif</li>
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
                <p class="hint">Edit questions and answer options directly below. Use a unique lower-case key for every question and option. Scores are from 0–100; mark a “not enough information” option as unscored. Founders’ scores are recalculated only from the approved version they used.</p>
                @include('partials.field', ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'rows' => 2, 'value' => $framework->description])
                @error('questions')<div class="alert alert-danger" role="alert">{{ is_array($message) ? implode(' ', $message) : $message }}</div>@enderror
                <div data-tabs="Assessment areas" data-tabs-key="vgi-dimensions-{{ $framework->id }}">
                @foreach ($definition['dimensions'] ?? [] as $dimension)
                    @php($dimensionKey = $dimension['key'])
                    <section class="panel vgi-question-dimension" data-tab="{{ $dimension['title'] }}" data-tab-icon="fa-seedling" data-tab-count="{{ count($dimension['questions']) }}" data-vgi-dimension="{{ $dimensionKey }}" aria-labelledby="dimension-{{ $dimensionKey }}">
                        <h3 id="dimension-{{ $dimensionKey }}">{{ $dimension['title'] }} <span class="meta">(weight {{ $dimension['weight'] }})</span></h3>
                        <div class="vgi-question-list">
                            @foreach ($questionsByDimension[$dimensionKey] ?? [] as $questionIndex => $question)
                                <fieldset class="panel vgi-question" data-vgi-question-index="{{ $questionIndex }}">
                                    <legend>Question {{ $loop->iteration }}</legend>
                                    <div class="form-row">
                                        @include('partials.field', ['name' => "questions[$dimensionKey][$questionIndex][key]", 'label' => 'Question key', 'value' => $question['key'] ?? '', 'required' => true])
                                        @include('partials.field', ['name' => "questions[$dimensionKey][$questionIndex][weight]", 'label' => 'Weight', 'type' => 'number', 'value' => $question['weight'] ?? 1, 'required' => true, 'attrs' => 'min="0.1" step="0.1"'])
                                    </div>
                                    @include('partials.field', ['name' => "questions[$dimensionKey][$questionIndex][prompt]", 'label' => 'Question', 'type' => 'textarea', 'rows' => 2, 'value' => $question['prompt'] ?? '', 'required' => true])
                                    @include('partials.field', ['name' => "questions[$dimensionKey][$questionIndex][help]", 'label' => 'Help text (optional)', 'type' => 'textarea', 'rows' => 2, 'value' => $question['help'] ?? ''])
                                    <div class="form-row">
                                        @include('partials.field', ['name' => "questions[$dimensionKey][$questionIndex][evidence]", 'label' => 'Evidence', 'type' => 'select', 'options' => ['none' => 'Not collected', 'optional' => 'Optional', 'required' => 'Required'], 'value' => $question['evidence'] ?? 'none'])
                                        @include('partials.field', ['name' => "questions[$dimensionKey][$questionIndex][evidence_hint]", 'label' => 'Evidence guidance (optional)', 'value' => $question['evidence_hint'] ?? ''])
                                    </div>
                                    <h4>Answer options (2–7)</h4>
                                    <div class="vgi-option-list">
                                        @foreach ($question['options'] ?? [] as $optionIndex => $option)
                                            @include('admin.vgi.partials.option-fields', ['dimensionKey' => $dimensionKey, 'questionIndex' => $questionIndex, 'optionIndex' => $optionIndex, 'option' => $option])
                                        @endforeach
                                    </div>
                                    <template data-vgi-option-template>
                                        @include('admin.vgi.partials.option-fields', ['dimensionKey' => $dimensionKey, 'questionIndex' => $questionIndex, 'optionIndex' => '__OPTION__', 'option' => ['key' => '', 'label' => '', 'score' => '', 'tip' => '', 'insufficient' => false], 'isTemplate' => true])
                                    </template>
                                    <div class="actions">
                                        <button type="button" class="btn btn-secondary btn-sm" data-vgi-add-option>Add option</button>
                                        <button type="button" class="btn btn-danger btn-sm" data-vgi-remove-question>Remove question</button>
                                    </div>
                                </fieldset>
                            @endforeach
                        </div>
                        <template data-vgi-question-template>
                            <fieldset class="panel vgi-question">
                                <legend>New question</legend>
                                <div class="form-row">
                                    @include('partials.field', ['name' => "questions[$dimensionKey][__QUESTION__][key]", 'label' => 'Question key', 'value' => '', 'required' => true])
                                    @include('partials.field', ['name' => "questions[$dimensionKey][__QUESTION__][weight]", 'label' => 'Weight', 'type' => 'number', 'value' => 1, 'required' => true, 'attrs' => 'min="0.1" step="0.1"'])
                                </div>
                                @include('partials.field', ['name' => "questions[$dimensionKey][__QUESTION__][prompt]", 'label' => 'Question', 'type' => 'textarea', 'rows' => 2, 'value' => '', 'required' => true])
                                @include('partials.field', ['name' => "questions[$dimensionKey][__QUESTION__][help]", 'label' => 'Help text (optional)', 'type' => 'textarea', 'rows' => 2, 'value' => ''])
                                <div class="form-row">
                                    @include('partials.field', ['name' => "questions[$dimensionKey][__QUESTION__][evidence]", 'label' => 'Evidence', 'type' => 'select', 'options' => ['none' => 'Not collected', 'optional' => 'Optional', 'required' => 'Required'], 'value' => 'none'])
                                    @include('partials.field', ['name' => "questions[$dimensionKey][__QUESTION__][evidence_hint]", 'label' => 'Evidence guidance (optional)', 'value' => ''])
                                </div>
                                <h4>Answer options (2–7)</h4>
                                <div class="vgi-option-list">
                                    @foreach ([['key' => 'a', 'label' => 'Option 1', 'score' => 0], ['key' => 'b', 'label' => 'Option 2', 'score' => 100]] as $optionIndex => $option)
                                        @include('admin.vgi.partials.option-fields', ['dimensionKey' => $dimensionKey, 'questionIndex' => '__QUESTION__', 'optionIndex' => $optionIndex, 'option' => $option, 'isTemplate' => true])
                                    @endforeach
                                </div>
                                <template data-vgi-option-template>
                                    @include('admin.vgi.partials.option-fields', ['dimensionKey' => $dimensionKey, 'questionIndex' => '__QUESTION__', 'optionIndex' => '__OPTION__', 'option' => ['key' => '', 'label' => '', 'score' => '', 'tip' => '', 'insufficient' => false], 'isTemplate' => true])
                                </template>
                                <div class="actions">
                                    <button type="button" class="btn btn-secondary btn-sm" data-vgi-add-option>Add option</button>
                                    <button type="button" class="btn btn-danger btn-sm" data-vgi-remove-question>Remove question</button>
                                </div>
                            </fieldset>
                        </template>
                        <button type="button" class="btn btn-secondary" data-vgi-add-question>Add question to {{ $dimension['title'] }}</button>
                    </section>
                @endforeach
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
