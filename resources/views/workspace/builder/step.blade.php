@extends('layouts.public', ['pageTitle' => $submission->step->title.' · Venture Builder'])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="container section">
        <p><a href="{{ route('workspace.builder', $startup) }}">← Venture Builder</a></p>
        <h1>{{ $submission->step->module->title }}: {{ $submission->step->title }}</h1>
        @include('workspace.startups.nav')
        @include('partials.flash')
        @if ($submission->step->guidance)<div class="panel">{!! \App\Support\Markdown::render($submission->step->guidance) !!}</div>@endif
        @if (!$membership->canEdit() || !$submission->isEditable())
            <p>This step is read-only for your role or current status.</p>
            @foreach ($submission->step->fields ?? [] as $field)<p><strong>{{ $field['label'] }}</strong><br>{{ $submission->responses[$field['key']] ?? '—' }}</p>@endforeach
        @else
            <form method="post" action="{{ route('workspace.builder.step.save', [$startup, $submission]) }}" class="panel">
                @csrf @method('PUT')
                <input type="hidden" name="version" value="{{ $submission->version }}">
                @foreach ($submission->step->fields ?? [] as $field)
                    <label for="field-{{ $field['key'] }}">{{ $field['label'] }} @if ($field['required'] ?? false)<span>(required)</span>@endif</label>
                    @if (($field['type'] ?? 'text') === 'textarea')
                        <textarea id="field-{{ $field['key'] }}" name="responses[{{ $field['key'] }}]" rows="5">{{ old('responses.'.$field['key'], $submission->responses[$field['key']] ?? '') }}</textarea>
                    @elseif (($field['type'] ?? '') === 'select')
                        <select id="field-{{ $field['key'] }}" name="responses[{{ $field['key'] }}]"><option value="">Choose…</option>@foreach (($field['options'] ?? []) as $value => $label)<option value="{{ $value }}" @selected(old('responses.'.$field['key'], $submission->responses[$field['key']] ?? '') === $value)>{{ $label }}</option>@endforeach</select>
                    @else
                        <input id="field-{{ $field['key'] }}" type="{{ in_array($field['type'] ?? '', ['number','url','date'], true) ? $field['type'] : 'text' }}" name="responses[{{ $field['key'] }}]" value="{{ old('responses.'.$field['key'], $submission->responses[$field['key']] ?? '') }}">
                    @endif
                @endforeach
                @foreach ($submission->step->checklist ?? [] as $item)
                    <label><input type="checkbox" name="checklist[{{ $item['key'] }}]" value="1" @checked(old('checklist.'.$item['key'], $submission->checklist_state[$item['key']] ?? false))> {{ $item['label'] }}</label>
                @endforeach
                <button class="btn btn-primary" type="submit">Save draft</button>
            </form>
            @if ($submission->status !== 'not_started')
                <form method="post" action="{{ route('workspace.builder.step.submit', [$startup, $submission]) }}">@csrf<button class="btn" type="submit">Submit step and unlock next</button></form>
            @endif
        @endif
            @if (($submission->step->evidence_requirement ?? 'none') !== 'none')
                <section class="panel" aria-labelledby="evidence-heading">
                    <h2 id="evidence-heading">Private evidence</h2>
                    <p>Evidence is visible only to this startup's authorized team and reviewers with access.</p>
                    @foreach ($submission->evidence as $evidence)
                        <div><strong>{{ $evidence->title }}</strong>
                            @if ($evidence->kind === 'file')<a href="{{ route('workspace.builder.step.evidence.download', [$startup, $submission, $evidence]) }}">Download {{ $evidence->original_name }}</a>
                            @else<a href="{{ $evidence->url }}" rel="noopener noreferrer">Open source</a>@endif
                            @if ($membership->canEdit())<form method="post" action="{{ route('workspace.builder.step.evidence.delete', [$startup, $submission, $evidence]) }}">@csrf @method('DELETE')<button type="submit">Remove</button></form>@endif
                        </div>
                    @endforeach
                    @if ($membership->canEdit() && $submission->isEditable())
                        <form method="post" enctype="multipart/form-data" action="{{ route('workspace.builder.step.evidence', [$startup, $submission]) }}">
                            @csrf<label for="evidence-title">Evidence title</label><input id="evidence-title" name="title" required maxlength="200">
                            <label for="evidence-file">Private file (up to {{ (int) (config('builder.evidence.max_kb') / 1024) }} MB)</label><input id="evidence-file" type="file" name="file" accept=".pdf,.docx,.xlsx,.xls,.pptx,.csv,.txt,.png,.jpg,.jpeg">
                            <label for="evidence-url">Or source link (HTTP/HTTPS)</label><input id="evidence-url" type="url" name="url">
                            <label for="evidence-note">Source note</label><textarea id="evidence-note" name="source_note" rows="2"></textarea>
                            <button class="btn" type="submit">Add evidence</button>
                        </form>
                    @endif
                </section>
            @endif
            @can('review-builder-steps')
                @if ($submission->status === 'submitted')
                    <section class="panel" aria-labelledby="review-heading">
                        <h2 id="review-heading">Reviewer decision</h2>
                        <form method="post" action="{{ route('workspace.builder.step.review', [$startup, $submission]) }}">
                            @csrf<label for="review-feedback">Feedback (at least 10 characters)</label><textarea id="review-feedback" name="feedback" rows="4" required minlength="10"></textarea>
                            <button class="btn" name="decision" value="changes_requested">Request changes</button>
                            <button class="btn btn-primary" name="decision" value="validated">Validate step</button>
                        </form>
                    </section>
                @endif
            @endcan
        @if ($submission->reviews->isNotEmpty())
            <section class="panel"><h2>Review history</h2>@foreach ($submission->reviews as $review)<p><strong>{{ $review->decision === 'validated' ? 'Validated' : 'Changes requested' }}</strong> — {{ $review->feedback }} <small>by {{ $review->reviewer?->name ?? 'former reviewer' }} at {{ $review->created_at }}</small></p>@endforeach</section>
        @endif
        @if ($submission->step->output_template)
            <section class="panel" aria-labelledby="document-heading">
                <h2 id="document-heading">Step output</h2>
                <p>Template-based draft only. Review and edit before using or sharing it.</p>
                @if ($submission->document?->current_version)
                    @php($document = $submission->document)
                    @php($currentDocumentVersion = $document->version())
                    <p>Current version {{ $document->current_version }} ({{ $currentDocumentVersion->source }}).</p>
                    <form method="post" action="{{ route('workspace.builder.step.document.save', [$startup, $submission, $document]) }}">
                        @csrf @method('PUT')<input type="hidden" name="expected_version" value="{{ $document->current_version }}">
                        <label for="document-body">Editable Markdown output</label>
                        <textarea id="document-body" name="body" rows="16" required>{{ old('body', $currentDocumentVersion->body) }}</textarea>
                        @if ($membership->canEdit())<button class="btn btn-primary" type="submit">Save new version</button>@endif
                        <a class="btn btn-secondary" href="{{ route('workspace.builder.step.document.export', [$startup, $submission, $document]) }}">Download Markdown</a>
                    </form>
                    <details><summary>Version history</summary><ol>@foreach ($document->versions as $version)<li>Version {{ $version->number }} · {{ $version->source }} · {{ $version->created_at }} · {{ $version->author?->name ?? 'former member' }}</li>@endforeach</ol></details>
                    <details><summary>Safe preview of current output</summary><div>{!! \App\Support\Markdown::render($currentDocumentVersion->body) !!}</div></details>
                @else
                    @if ($membership->canEdit())
                        <form method="post" action="{{ route('workspace.builder.step.document.generate', [$startup, $submission]) }}">@csrf<button class="btn btn-primary" type="submit">Generate template draft</button></form>
                    @else<p>No step output has been generated yet.</p>@endif
                @endif
            </section>
        @endif
    </div>
@endsection
