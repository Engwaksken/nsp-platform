@php
    $optionPrefix = "questions[$dimensionKey][$questionIndex][options][$optionIndex]";
    $templateClass = !empty($isTemplate) ? ' vgi-option-template' : '';
@endphp
<fieldset class="vgi-option{{ $templateClass }}" data-vgi-option data-vgi-option-index="{{ $optionIndex }}">
    <legend>Answer option</legend>
    <div class="form-row">
        @include('partials.field', ['name' => $optionPrefix.'[key]', 'label' => 'Option key', 'value' => $option['key'] ?? '', 'required' => true])
        @include('partials.field', ['name' => $optionPrefix.'[label]', 'label' => 'Answer text', 'value' => $option['label'] ?? '', 'required' => true])
        @include('partials.field', ['name' => $optionPrefix.'[score]', 'label' => 'Score (0–100)', 'type' => 'number', 'value' => $option['score'] ?? '', 'attrs' => 'min="0" max="100" step="0.1"'])
    </div>
    <label class="checkbox"><input type="checkbox" name="{{ $optionPrefix }}[insufficient]" value="1" @checked($option['insufficient'] ?? false)> <span>Not enough information (do not score)</span></label>
    @include('partials.field', ['name' => $optionPrefix.'[tip]', 'label' => 'Tip shown after a weak answer (optional)', 'value' => $option['tip'] ?? ''])
    <button type="button" class="btn btn-secondary btn-sm" data-vgi-remove-option>Remove option</button>
</fieldset>
