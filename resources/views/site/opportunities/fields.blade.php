{{-- Opportunity fields shared by the public submission form and the admin editor. Expects $opportunity, $staff (bool). --}}
@php($rules = $opportunity->eligibility ?? [])
{{-- Tabs keep the long form short on screen (and in the admin popup); a field with an error opens its tab. --}}
<div data-tabs="Opportunity fields" data-tabs-key="opportunity-fields">
<section class="panel" aria-labelledby="opp-details" data-tab="Details" data-tab-icon="fa-circle-info">
    <h2 id="opp-details">Details</h2>
    <div class="form-row">
        @include('partials.field', ['name' => 'type', 'label' => 'Type', 'type' => 'select', 'options' => \App\Models\Ecosystem\Opportunity::TYPES, 'value' => $opportunity->type, 'required' => true])
        @include('partials.field', ['name' => 'organisation', 'label' => 'Organiser', 'value' => $opportunity->organisation, 'maxlength' => 160])
    </div>
    @include('partials.field', ['name' => 'title', 'label' => 'Title', 'value' => $opportunity->title, 'required' => true, 'maxlength' => 200])
    @include('partials.field', ['name' => 'summary', 'label' => 'Short summary', 'type' => 'textarea', 'rows' => 2, 'value' => $opportunity->summary, 'required' => true, 'hint' => 'One or two sentences shown in the list.'])
    @include('partials.field', ['name' => 'body', 'label' => 'Full description (Markdown)', 'type' => 'textarea', 'rows' => 8, 'value' => $opportunity->body])
</section>

<section class="panel" aria-labelledby="opp-when" data-tab="Value, place and dates" data-tab-icon="fa-calendar-days">
    <h2 id="opp-when">Value, place and dates</h2>
    <div class="form-row">
        @include('partials.field', ['name' => 'amount_text', 'label' => 'Value or prize', 'value' => $opportunity->amount_text, 'maxlength' => 120, 'hint' => 'For example “Up to UGX 20M” or “Free”.'])
        @include('partials.field', ['name' => 'location', 'label' => 'Location', 'value' => $opportunity->location, 'maxlength' => 160])
        <div class="form-group"><label class="checkbox"><input type="checkbox" name="is_online" value="1" @checked(old('is_online', $opportunity->is_online))> <span>Online</span></label></div>
    </div>
    <div class="form-row">
        @include('partials.field', ['name' => 'deadline_at', 'label' => 'Application deadline', 'type' => 'datetime-local', 'value' => $opportunity->deadline_at?->format('Y-m-d\TH:i')])
        @include('partials.field', ['name' => 'starts_at', 'label' => 'Starts (events)', 'type' => 'datetime-local', 'value' => $opportunity->starts_at?->format('Y-m-d\TH:i')])
        @include('partials.field', ['name' => 'ends_at', 'label' => 'Ends (events)', 'type' => 'datetime-local', 'value' => $opportunity->ends_at?->format('Y-m-d\TH:i')])
    </div>
    @include('partials.field', ['name' => 'apply_url', 'label' => 'Where to apply (link)', 'type' => 'url', 'value' => $opportunity->apply_url, 'hint' => 'The organiser’s own application page, if any.'])
</section>

<section class="panel" aria-labelledby="opp-eligibility" data-tab="Who can apply" data-tab-icon="fa-user-check">
    <h2 id="opp-eligibility">Who can apply</h2>
    <p class="hint">Leave a group empty if it is open to everyone. Founders see whether their startup matches.</p>
    <fieldset class="form-group">
        <legend class="label">Sectors</legend>
        <div class="form-row">
            @foreach (\App\Models\Registry\StartupProfile::SECTORS as $sector)
                <label class="checkbox"><input type="checkbox" name="sectors[]" value="{{ $sector }}" @checked(in_array($sector, old('sectors', $rules['sectors'] ?? []), true))> <span>{{ $sector }}</span></label>
            @endforeach
        </div>
    </fieldset>
    <fieldset class="form-group">
        <legend class="label">Countries</legend>
        <div class="form-row">
            @foreach (\App\Models\Registry\Startup::COUNTRIES as $code => $name)
                <label class="checkbox"><input type="checkbox" name="countries[]" value="{{ $code }}" @checked(in_array($code, old('countries', $rules['countries'] ?? []), true))> <span>{{ $name }}</span></label>
            @endforeach
        </div>
    </fieldset>
    @php($stages = collect(\App\Models\Growth\VgiFramework::current()?->definition['bands'] ?? [])->pluck('label')->all() ?: ['Idea', 'Validation', 'Early traction', 'Growth', 'Scale'])
    <fieldset class="form-group">
        <legend class="label">Growth stages</legend>
        <div class="form-row">
            @foreach ($stages as $stage)
                <label class="checkbox"><input type="checkbox" name="stages[]" value="{{ $stage }}" @checked(in_array($stage, old('stages', $rules['stages'] ?? []), true))> <span>{{ $stage }}</span></label>
            @endforeach
        </div>
    </fieldset>
    <div class="form-row">
        <div class="form-group"><label class="checkbox"><input type="checkbox" name="registered_only" value="1" @checked(old('registered_only', $rules['registered_only'] ?? false))> <span>Registered companies only</span></label></div>
        @include('partials.field', ['name' => 'max_age_years', 'label' => 'Founded within (years)', 'type' => 'number', 'value' => $rules['max_age_years'] ?? null, 'attrs' => 'min="1" max="50"'])
    </div>
</section>

@if ($staff)
    <section class="panel" aria-labelledby="opp-registration" data-tab="Registration on NSP" data-tab-icon="fa-clipboard-list">
        <h2 id="opp-registration">Registration on NSP</h2>
        <div class="form-row">
            <div class="form-group"><label class="checkbox"><input type="checkbox" name="accepts_interest" value="1" @checked(old('accepts_interest', $opportunity->accepts_interest))> <span>Let people register or express interest here</span></label></div>
            @include('partials.field', ['name' => 'capacity', 'label' => 'Places (optional)', 'type' => 'number', 'value' => $opportunity->capacity, 'attrs' => 'min="1"'])
        </div>
    </section>
@endif
</div>
