{{-- Choose an uploaded image. Expects $name, $label, $value, $media (collection). --}}
@php($id = 'field-' . $name)
<div class="form-group">
    <label for="{{ $id }}">{{ $label }}</label>
    <select id="{{ $id }}" name="{{ $name }}" @error($name) aria-invalid="true" @enderror>
        <option value="">None</option>
        @foreach ($media as $file)
            <option value="{{ $file->id }}" @selected((string) old($name, $value) === (string) $file->id)>{{ $file->original_name }}@if ($file->alt_text) ({{ $file->alt_text }})@endif</option>
        @endforeach
    </select>
    <p class="hint">Upload images in the <a href="{{ route('admin.media.index') }}">media library</a> first.</p>
    @error($name)<p class="error">{{ $message }}</p>@enderror
</div>
