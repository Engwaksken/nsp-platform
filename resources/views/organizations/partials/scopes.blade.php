{{-- Scope checkboxes. Expects $idPrefix; optional $only (limit to these keys) and $checked (pre-ticked keys). --}}
<fieldset class="form-group">
    <legend class="label">What may they see?</legend>
    @foreach (config('organizations.scopes') as $key => $scope)
        @continue(isset($only) && !in_array($key, $only, true))
        <label class="checkbox">
            <input type="checkbox" name="scopes[]" value="{{ $key }}" id="{{ $idPrefix }}-{{ $key }}" @checked(in_array($key, old('scopes', $checked ?? []), true))>
            <span><strong>{{ $scope['label'] }}</strong> · <span class="meta">{{ $scope['description'] }}</span></span>
        </label>
    @endforeach
    @error('scopes')<p class="error">{{ $message }}</p>@enderror
</fieldset>
