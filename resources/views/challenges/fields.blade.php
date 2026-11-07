{{-- Challenge content fields, shared by the member form and the admin editor. Expects $challenge and $staff. --}}
@include('partials.field', ['name' => 'title', 'label' => 'Title', 'value' => $challenge->title, 'required' => true, 'maxlength' => 200])
@include('partials.field', ['name' => 'problem_description', 'label' => 'Problem description', 'type' => 'textarea', 'rows' => 8, 'value' => $challenge->problem_description, 'required' => true,
    'hint' => 'Who is affected, how, and how big the problem is. Markdown formatting is allowed.'])
<div class="form-row">
    @include('partials.field', ['name' => 'category', 'label' => 'Category', 'type' => 'select', 'options' => config('challenges.categories'), 'value' => $challenge->category, 'required' => true])
    @include('partials.field', ['name' => 'priority', 'label' => 'Priority', 'type' => 'select', 'options' => config('challenges.priorities'), 'value' => $challenge->priority, 'required' => true])
    @include('partials.field', ['name' => 'sector', 'label' => 'Sector', 'type' => 'select', 'placeholder' => 'Not sector specific', 'options' => array_combine(\App\Models\Registry\StartupProfile::SECTORS, \App\Models\Registry\StartupProfile::SECTORS), 'value' => $challenge->sector])
</div>
<div class="form-row">
    @include('partials.field', ['name' => 'country', 'label' => 'Country', 'type' => 'select', 'placeholder' => 'Any / several', 'options' => \App\Models\Registry\Startup::COUNTRIES, 'value' => $challenge->country])
    @include('partials.field', ['name' => 'region', 'label' => 'Region or district', 'value' => $challenge->region, 'maxlength' => 120])
    @include('partials.field', ['name' => 'geography', 'label' => 'Geography (free text)', 'value' => $challenge->geography, 'maxlength' => 200, 'hint' => 'For example “Lake Victoria fishing communities”.'])
</div>
@include('partials.field', ['name' => 'market_opportunity', 'label' => 'Market opportunity', 'type' => 'textarea', 'rows' => 4, 'value' => $challenge->market_opportunity])
@include('partials.field', ['name' => 'existing_solutions', 'label' => 'Existing solutions and why they fall short', 'type' => 'textarea', 'rows' => 4, 'value' => $challenge->existing_solutions])
@include('partials.field', ['name' => 'policy_considerations', 'label' => 'Policy and regulatory considerations', 'type' => 'textarea', 'rows' => 4, 'value' => $challenge->policy_considerations])

<fieldset class="panel">
    <legend>Research references</legend>
    <p class="hint">Published studies, statistics or reports. Each needs a title; a link is optional.</p>
    @php($refs = old('references', $challenge->research_references ?? []))
    @php($refs = array_values(array_merge((array) $refs, array_fill(0, 2, ['title' => '', 'url' => '', 'accessed' => '']))))
    @foreach ($refs as $i => $ref)
        <div class="form-row">
            @include('partials.field', ['name' => "references[$i][title]", 'label' => 'Title', 'value' => $ref['title'] ?? ''])
            @include('partials.field', ['name' => "references[$i][url]", 'label' => 'Link', 'type' => 'url', 'value' => $ref['url'] ?? ''])
            @include('partials.field', ['name' => "references[$i][accessed]", 'label' => 'Accessed on', 'type' => 'date', 'value' => $ref['accessed'] ?? ''])
        </div>
    @endforeach
</fieldset>

<div class="form-row">
    @include('partials.field', ['name' => 'issuer_name', 'label' => 'Issuer name', 'value' => $challenge->issuer_name, 'maxlength' => 160, 'hint' => 'The organisation that owns this challenge.'])
    @include('partials.field', ['name' => 'issuer_type', 'label' => 'Issuer type', 'type' => 'select', 'placeholder' => 'Choose', 'options' => config('challenges.issuer_types'), 'value' => $challenge->issuer_type])
    @include('partials.field', ['name' => 'visibility', 'label' => 'Who can see it once published', 'type' => 'select', 'options' => \App\Models\Challenges\Challenge::VISIBILITIES, 'value' => $challenge->visibility, 'required' => true])
</div>
@include('partials.field', ['name' => 'tags', 'label' => 'Tags', 'value' => implode(', ', (array) $challenge->tags), 'hint' => 'Separate with commas, up to 20.'])

<details class="panel">
    <summary>Links to the Venture Builder, Growth Index and experts (optional)</summary>
    @include('partials.field', ['name' => 'vgi_dimension_keys', 'label' => 'Growth Index dimension keys', 'value' => implode(', ', (array) $challenge->vgi_dimension_keys), 'hint' => 'Comma separated keys, for example market_validation.'])
    @include('partials.field', ['name' => 'builder_step_keys', 'label' => 'Venture Builder step keys', 'value' => implode(', ', (array) $challenge->builder_step_keys)])
    @include('partials.field', ['name' => 'expert_categories', 'label' => 'Expert category keys', 'value' => implode(', ', (array) $challenge->expert_categories)])
    @if ($staff)
        @include('partials.field', ['name' => 'organization_id', 'label' => 'Organization ID (optional)', 'type' => 'number', 'value' => $challenge->organization_id])
    @endif
</details>
