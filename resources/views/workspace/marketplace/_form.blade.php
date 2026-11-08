{{-- Listing fields. Expects $listing. --}}
<fieldset class="form-group">
    <legend class="label">What kind of listing?</legend>
    @foreach (\App\Models\Marketplace\Listing::KINDS as $value => $label)
        <label class="checkbox"><input type="radio" name="kind" value="{{ $value }}" @checked(old('kind', $listing->kind ?? 'offer') === $value) required>
            <span><strong>{{ $label }}</strong>: {{ \App\Models\Marketplace\Listing::KIND_HINTS[$value] }}</span></label>
    @endforeach
    @error('kind')<p class="error">{{ $message }}</p>@enderror
</fieldset>
@include('partials.field', ['name' => 'title', 'label' => 'Title', 'required' => true, 'maxlength' => 150, 'value' => $listing->title])
@include('partials.field', ['name' => 'category', 'label' => 'Category', 'type' => 'select', 'required' => true, 'placeholder' => 'Choose…', 'value' => $listing->category, 'options' => \App\Models\Marketplace\Listing::categories()])
@include('partials.field', ['name' => 'summary', 'label' => 'One-line summary', 'required' => true, 'maxlength' => 300, 'value' => $listing->summary])
@include('partials.field', ['name' => 'description', 'label' => 'Details', 'type' => 'textarea', 'rows' => 6, 'required' => true, 'value' => $listing->description, 'hint' => 'Do not include bank details. Agree payment directly with the other side.'])
<div class="grid">
    @include('partials.field', ['name' => 'price_note', 'label' => 'Price or budget (optional)', 'maxlength' => 150, 'value' => $listing->price_note])
    @include('partials.field', ['name' => 'location', 'label' => 'Location or delivery area', 'maxlength' => 120, 'value' => $listing->location])
    @include('partials.field', ['name' => 'expires_on', 'label' => 'Show until (optional)', 'type' => 'date', 'value' => $listing->expires_on?->toDateString()])
</div>
