{{--
    One labelled form field with its error.
    @include('partials.field', ['name' => 'title', 'label' => 'Title', 'value' => $page->title, 'type' => 'text', 'required' => true, 'hint' => '...'])

    Placeholders:
    - select:          'placeholder' => 'Choose…' adds an empty first option (unchanged behaviour; none is added otherwise).
    - 'inputClass' => 'otp-input' adds a class to an <input>.
    - input/textarea:  a helpful placeholder is derived from the label and type. Pass 'placeholder' => 'e.g. …' to set it,
                       or 'placeholder' => false to show none.
--}}
@php
    $type = $type ?? 'text';
    $id = $id ?? 'field-' . str_replace(['[', ']', '.'], '-', $name);
    $errorKey = str_replace(['[', ']'], ['.', ''], $name);
    $current = old($errorKey, $value ?? '');
    $describedBy = trim((isset($hint) ? $id . '-hint ' : '') . ($errors->has($errorKey) ? $id . '-error' : ''));

    // Selects use $placeholder as an empty first option (below); text-like fields get it as placeholder text.
    $inputPlaceholder = $type === 'select' ? null : ($placeholder ?? \App\Support\FieldPlaceholder::for($label, $type));
    if (!is_string($inputPlaceholder) || $inputPlaceholder === '') {
        $inputPlaceholder = null;
    }
@endphp
<div class="form-group">
    <label for="{{ $id }}" @class(['required' => $required ?? false])>{{ $label }}</label>
    @if ($type === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows ?? 5 }}" @if ($required ?? false) required @endif
                  @if ($inputPlaceholder) placeholder="{{ $inputPlaceholder }}" @endif
                  @class(['is-invalid' => $errors->has($errorKey)]) @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                  @if ($errors->has($errorKey)) aria-invalid="true" @endif>{{ $current }}</textarea>
    @elseif ($type === 'select')
        <select id="{{ $id }}" name="{{ $name }}" @if ($required ?? false) required @endif @class(['is-invalid' => $errors->has($errorKey)])
                @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif @if ($errors->has($errorKey)) aria-invalid="true" @endif>
            @if ($placeholder ?? false)<option value="">{{ $placeholder }}</option>@endif
            @foreach ($options as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" @selected((string) $current === (string) $optionValue)>{{ $optionLabel }}</option>
            @endforeach
        </select>
    @else
        <input id="{{ $id }}" type="{{ $type }}" name="{{ $name }}" value="{{ $current }}"
               @if ($required ?? false) required @endif @if ($autocomplete ?? false) autocomplete="{{ $autocomplete }}" @endif
               @if ($maxlength ?? false) maxlength="{{ $maxlength }}" @endif
               @if ($inputPlaceholder && !str_contains($attrs ?? '', 'placeholder=')) placeholder="{{ $inputPlaceholder }}" @endif
               @if ($attrs ?? false) {!! $attrs !!} @endif {{-- developer-supplied attributes only, never user input --}}
               @class([$inputClass ?? '' => filled($inputClass ?? null), 'is-invalid' => $errors->has($errorKey)])
               @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif @if ($errors->has($errorKey)) aria-invalid="true" @endif>
    @endif
    @isset($hint)<p class="hint" id="{{ $id }}-hint">{{ $hint }}</p>@endisset
    @error($errorKey)<p class="error" id="{{ $id }}-error">{{ $message }}</p>@enderror
</div>
