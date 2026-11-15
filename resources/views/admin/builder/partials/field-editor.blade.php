<fieldset class="curriculum-response-field" data-curriculum-field data-field-index="{{ $fieldIndex }}">
    <legend>Founder response field</legend>
    <div class="form-row">
        @include('partials.field', ['name' => "definition[modules][$moduleIndex][steps][$stepIndex][fields][$fieldIndex][key]", 'label' => 'Field key', 'value' => $field['key'] ?? '', 'required' => true])
        @include('partials.field', ['name' => "definition[modules][$moduleIndex][steps][$stepIndex][fields][$fieldIndex][label]", 'label' => 'Question / label', 'value' => $field['label'] ?? '', 'required' => true])
        @include('partials.field', ['name' => "definition[modules][$moduleIndex][steps][$stepIndex][fields][$fieldIndex][type]", 'label' => 'Response type', 'type' => 'select', 'options' => array_combine(config('builder.field_types'), config('builder.field_types')), 'value' => $field['type'] ?? 'textarea'])
    </div>
    @include('partials.field', ['name' => "definition[modules][$moduleIndex][steps][$stepIndex][fields][$fieldIndex][help]", 'label' => 'Help text (optional)', 'value' => $field['help'] ?? ''])
    @include('partials.field', ['name' => "definition[modules][$moduleIndex][steps][$stepIndex][fields][$fieldIndex][options_text]", 'label' => 'Select choices (one “key | label” per line)', 'type' => 'textarea', 'rows' => 2, 'value' => $field['options_text'] ?? ''])
    <input type="hidden" name="definition[modules][{{ $moduleIndex }}][steps][{{ $stepIndex }}][fields][{{ $fieldIndex }}][required]" value="0">
    <label class="checkbox"><input type="checkbox" name="definition[modules][{{ $moduleIndex }}][steps][{{ $stepIndex }}][fields][{{ $fieldIndex }}][required]" value="1" @checked($field['required'] ?? false)> <span>Required</span></label>
    <button type="button" class="btn btn-secondary btn-sm" data-curriculum-remove-field>Remove response field</button>
</fieldset>
