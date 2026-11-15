<fieldset class="panel curriculum-module" data-curriculum-module data-module-index="{{ $moduleIndex }}">
    <legend>Module</legend>
    <div class="form-row">
        @include('partials.field', ['name' => "definition[modules][$moduleIndex][key]", 'label' => 'Module key', 'value' => $module['key'] ?? '', 'required' => true])
        @include('partials.field', ['name' => "definition[modules][$moduleIndex][title]", 'label' => 'Module title', 'value' => $module['title'] ?? '', 'required' => true])
    </div>
    @include('partials.field', ['name' => "definition[modules][$moduleIndex][description]", 'label' => 'Description', 'type' => 'textarea', 'rows' => 2, 'value' => $module['description'] ?? ''])
    @include('partials.field', ['name' => "definition[modules][$moduleIndex][outputs_text]", 'label' => 'Expected outputs (one per line)', 'type' => 'textarea', 'rows' => 2, 'value' => $module['outputs_text'] ?? ''])
    <input type="hidden" name="definition[modules][{{ $moduleIndex }}][active]" value="0">
    <label class="checkbox"><input type="checkbox" name="definition[modules][{{ $moduleIndex }}][active]" value="1" @checked($module['active'] ?? true)> <span>Module is active</span></label>
    <div class="curriculum-step-list" data-curriculum-step-list>
        @foreach ($module['steps'] ?? [] as $stepIndex => $step)
            @include('admin.builder.partials.step-editor', ['moduleIndex' => $moduleIndex, 'stepIndex' => $stepIndex, 'step' => $step])
        @endforeach
    </div>
    <template data-curriculum-step-template>
        @include('admin.builder.partials.step-editor', ['moduleIndex' => $moduleIndex, 'stepIndex' => '__STEP__', 'step' => ['key' => '', 'title' => '', 'fields' => [['key' => 'response', 'label' => 'Your response', 'type' => 'textarea', 'required' => true]], 'checklist' => [], 'active' => true]])
    </template>
    <div class="actions">
        <button type="button" class="btn btn-secondary" data-curriculum-add-step>Add step</button>
        <button type="button" class="btn btn-danger btn-sm" data-curriculum-remove-module>Remove module</button>
    </div>
</fieldset>
