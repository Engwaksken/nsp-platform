{{--
    One labelled form field with its error.
    @include('partials.field', ['name' => 'title', 'label' => 'Title', 'value' => $page->title, 'type' => 'text', 'required' => true, 'hint' => '...'])
--}}
@php
    $type = $type ?? 'text';
    $id = $id ?? 'field-' . str_replace(['[', ']', '.'], '-', $name);
    $errorKey = str_replace(['[', ']'], ['.', ''], $name);
    $current = old($errorKey, $value ?? '');
    $describedBy = trim((isset($hint) ? $id . '-hint ' : '') . ($errors->has($errorKey) ? $id . '-error' : ''));
@endphp
<div class="form-group">
    <label for="{{ $id }}" @class(['required' => $required ?? false])>{{ $label }}</label>
    @if ($type === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows ?? 5 }}" @if ($required ?? false) required @endif
                  @class(['is-invalid' => $errors->has($errorKey)]) @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                  @if ($errors->has($errorKey)) aria-invalid="true" @endif>{{ $current }}</textarea>
    @elseif ($type === 'select')
        <select id="{{ $id }}" name="{{ $name }}" @if ($required ?? false) required @endif @class(['is-invalid' => $errors->has($errorKey)])
                @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif>
            @if ($placeholder ?? false)<option value="">{{ $placeholder }}</option>@endif
            @foreach ($options as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" @selected((string) $current === (string) $optionValue)>{{ $optionLabel }}</option>
            @endforeach
        </select>
    @else
        <input id="{{ $id }}" type="{{ $type }}" name="{{ $name }}" value="{{ $current }}"
               @if ($required ?? false) required @endif @if ($autocomplete ?? false) autocomplete="{{ $autocomplete }}" @endif
               @if ($maxlength ?? false) maxlength="{{ $maxlength }}" @endif
               @if ($attrs ?? false) {!! $attrs !!} @endif {{-- developer-supplied attributes only, never user input --}}
               @class(['is-invalid' => $errors->has($errorKey)])
               @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif @if ($errors->has($errorKey)) aria-invalid="true" @endif>
    @endif
    @isset($hint)<p class="hint" id="{{ $id }}-hint">{{ $hint }}</p>@endisset
    @error($errorKey)<p class="error" id="{{ $id }}-error">{{ $message }}</p>@enderror
</div>
