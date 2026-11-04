{{--
    A labelled group of checkboxes.
    @include('partials.checks', ['name' => 'sectors', 'label' => 'Sectors', 'options' => [value => label], 'selected' => [...], 'hint' => '...'])
--}}
@php
    $selectedValues = array_map('strval', (array) old($name, $selected ?? []));
    $groupId = 'checks-'.$name;
@endphp
<fieldset class="form-group" @isset($hint) aria-describedby="{{ $groupId }}-hint" @endisset>
    <legend class="label">{{ $label }}</legend>
    @isset($hint)<p class="hint" id="{{ $groupId }}-hint">{{ $hint }}</p>@endisset
    @forelse ($options as $value => $optionLabel)
        <label class="checkbox"><input type="checkbox" name="{{ $name }}[]" value="{{ $value }}" @checked(in_array((string) $value, $selectedValues, true))> {{ $optionLabel }}</label>
    @empty
        <p class="hint">{{ $emptyText ?? 'No options yet.' }}</p>
    @endforelse
    @error($name)<p class="error">{{ $message }}</p>@enderror
    @error($name.'.*')<p class="error">{{ $message }}</p>@enderror
</fieldset>
