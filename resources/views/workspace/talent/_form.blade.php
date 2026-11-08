{{-- Vacancy fields. Expects $vacancy. --}}
@include('partials.field', ['name' => 'title', 'label' => 'Role title', 'required' => true, 'maxlength' => 150, 'value' => $vacancy->title])
<div class="grid">
    @include('partials.field', ['name' => 'type', 'label' => 'Type', 'type' => 'select', 'required' => true, 'value' => $vacancy->type ?? 'full_time', 'options' => \App\Models\Talent\Vacancy::TYPES])
    @include('partials.field', ['name' => 'closes_on', 'label' => 'Applications close on (optional)', 'type' => 'date', 'value' => $vacancy->closes_on?->toDateString()])
</div>
@include('partials.field', ['name' => 'description', 'label' => 'About the role', 'type' => 'textarea', 'rows' => 6, 'required' => true, 'value' => $vacancy->description])
@include('partials.field', ['name' => 'requirements', 'label' => 'What the person needs', 'type' => 'textarea', 'rows' => 4, 'value' => $vacancy->requirements])
@include('partials.field', ['name' => 'skills', 'label' => 'Key skills', 'value' => implode(', ', $vacancy->skills ?? []), 'hint' => 'Separate with commas.'])
<div class="grid">
    @include('partials.field', ['name' => 'location', 'label' => 'Location', 'maxlength' => 120, 'value' => $vacancy->location])
    @include('partials.field', ['name' => 'pay_note', 'label' => 'Pay (optional)', 'maxlength' => 150, 'value' => $vacancy->pay_note, 'hint' => 'For example: “UGX 1.2M a month” or “Stipend”.'])
</div>
<div class="form-group">
    <input type="hidden" name="remote" value="0">
    <label class="checkbox"><input type="checkbox" name="remote" value="1" @checked(old('remote', $vacancy->remote))> <span>Remote work possible</span></label>
</div>
