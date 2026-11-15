<fieldset class="panel curriculum-step" data-curriculum-step data-step-index="{{ $stepIndex }}">
    <legend>Step</legend>
    <div class="form-row">
        @include('partials.field', ['name' => "definition[modules][$moduleIndex][steps][$stepIndex][key]", 'label' => 'Step key', 'value' => $step['key'] ?? '', 'required' => true])
        @include('partials.field', ['name' => "definition[modules][$moduleIndex][steps][$stepIndex][title]", 'label' => 'Step title', 'value' => $step['title'] ?? '', 'required' => true])
    </div>
    @include('partials.field', ['name' => "definition[modules][$moduleIndex][steps][$stepIndex][guidance]", 'label' => 'Guidance', 'type' => 'textarea', 'rows' => 3, 'value' => $step['guidance'] ?? ''])
    <div class="form-row">
        @include('partials.field', ['name' => "definition[modules][$moduleIndex][steps][$stepIndex][evidence_requirement]", 'label' => 'Evidence requirement', 'type' => 'select', 'options' => config('builder.evidence_requirements'), 'value' => $step['evidence_requirement'] ?? 'none'])
        @include('partials.field', ['name' => "definition[modules][$moduleIndex][steps][$stepIndex][prerequisites_text]", 'label' => 'Prerequisite step keys (comma separated)', 'value' => $step['prerequisites_text'] ?? ''])
    </div>
    <div class="curriculum-field-list" data-curriculum-field-list>
        @foreach ($step['fields'] ?? [] as $fieldIndex => $field)
            @include('admin.builder.partials.field-editor', ['moduleIndex' => $moduleIndex, 'stepIndex' => $stepIndex, 'fieldIndex' => $fieldIndex, 'field' => $field])
        @endforeach
    </div>
    <template data-curriculum-field-template>
        @include('admin.builder.partials.field-editor', ['moduleIndex' => $moduleIndex, 'stepIndex' => $stepIndex, 'fieldIndex' => '__FIELD__', 'field' => ['key' => '', 'label' => '', 'type' => 'textarea', 'required' => true]])
    </template>
    <button type="button" class="btn btn-secondary btn-sm" data-curriculum-add-field>Add response field</button>
    <h4>Checklist</h4>
    <div class="curriculum-checklist" data-curriculum-checklist>
        @foreach ($step['checklist'] ?? [] as $checkIndex => $check)
            <div class="form-row curriculum-check" data-curriculum-check data-check-index="{{ $checkIndex }}">
                @include('partials.field', ['name' => "definition[modules][$moduleIndex][steps][$stepIndex][checklist][$checkIndex][key]", 'label' => 'Checklist key', 'value' => $check['key'] ?? '', 'required' => true])
                @include('partials.field', ['name' => "definition[modules][$moduleIndex][steps][$stepIndex][checklist][$checkIndex][label]", 'label' => 'Checklist item', 'value' => $check['label'] ?? '', 'required' => true])
                <button type="button" class="btn btn-secondary btn-sm" data-curriculum-remove-check>Remove</button>
            </div>
        @endforeach
    </div>
    <template data-curriculum-check-template>
        <div class="form-row curriculum-check" data-curriculum-check data-check-index="__CHECK__">
            @include('partials.field', ['name' => "definition[modules][$moduleIndex][steps][$stepIndex][checklist][__CHECK__][key]", 'label' => 'Checklist key', 'value' => '', 'required' => true])
            @include('partials.field', ['name' => "definition[modules][$moduleIndex][steps][$stepIndex][checklist][__CHECK__][label]", 'label' => 'Checklist item', 'value' => '', 'required' => true])
            <button type="button" class="btn btn-secondary btn-sm" data-curriculum-remove-check>Remove</button>
        </div>
    </template>
    <button type="button" class="btn btn-secondary btn-sm" data-curriculum-add-check>Add checklist item</button>
    <div class="form-row">
        @include('partials.field', ['name' => "definition[modules][$moduleIndex][steps][$stepIndex][task_suggestions_text]", 'label' => 'Suggested tasks (one per line)', 'type' => 'textarea', 'rows' => 2, 'value' => $step['task_suggestions_text'] ?? ''])
        @include('partials.field', ['name' => "definition[modules][$moduleIndex][steps][$stepIndex][output_template]", 'label' => 'Output template (optional)', 'type' => 'textarea', 'rows' => 2, 'value' => $step['output_template'] ?? ''])
    </div>
    @include('partials.field', ['name' => "definition[modules][$moduleIndex][steps][$stepIndex][vgi_dimension_keys]", 'label' => 'Related Growth Assessment dimensions (comma-separated keys)', 'value' => is_array($step['vgi_dimension_keys'] ?? null) ? implode(', ', $step['vgi_dimension_keys']) : ($step['vgi_dimension_keys'] ?? '')])
    <input type="hidden" name="definition[modules][{{ $moduleIndex }}][steps][{{ $stepIndex }}][active]" value="0">
    <label class="checkbox"><input type="checkbox" name="definition[modules][{{ $moduleIndex }}][steps][{{ $stepIndex }}][active]" value="1" @checked($step['active'] ?? true)> <span>Step is active</span></label>
    <input type="hidden" name="definition[modules][{{ $moduleIndex }}][steps][{{ $stepIndex }}][requires_review]" value="0">
    <label class="checkbox"><input type="checkbox" name="definition[modules][{{ $moduleIndex }}][steps][{{ $stepIndex }}][requires_review]" value="1" @checked($step['requires_review'] ?? false)> <span>Requires staff review</span></label>
    <button type="button" class="btn btn-danger btn-sm" data-curriculum-remove-step>Remove step</button>
</fieldset>
