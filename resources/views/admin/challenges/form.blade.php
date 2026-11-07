@extends('layouts.admin')
@section('title', $challenge->exists ? 'Edit challenge' : 'New challenge')

@section('content')
    <div class="page-head"><h1>{{ $challenge->exists ? 'Edit challenge' : 'New challenge' }}</h1>
        @if ($challenge->exists)<a class="btn btn-secondary" href="{{ route('admin.challenges.show', $challenge) }}">Moderation history</a>@endif
    </div>
    @include('partials.flash')
    @if ($challenge->published_version_id)<p class="alert alert-info">This edits a working copy. Published visitors keep seeing the approved snapshot until a new version is reviewed and published.</p>@endif
    <form method="post" action="{{ $challenge->exists ? route('admin.challenges.update', $challenge) : route('admin.challenges.store') }}" class="panel">
        @csrf @if ($challenge->exists) @method('PUT') @endif
        @include('partials.field', ['name' => 'title', 'label' => 'Challenge title', 'required' => true, 'maxlength' => 200, 'value' => old('title', $challenge->title)])
        @include('partials.field', ['name' => 'problem_description', 'label' => 'Problem description', 'type' => 'textarea', 'rows' => 6, 'required' => true, 'value' => old('problem_description', $challenge->problem_description)])
        <div class="form-row">
            @include('partials.field', ['name' => 'sector', 'label' => 'Sector', 'type' => 'select', 'options' => ['' => 'Choose a sector'] + array_combine(\App\Models\Registry\StartupProfile::SECTORS, \App\Models\Registry\StartupProfile::SECTORS), 'value' => old('sector', $challenge->sector)])
            @include('partials.field', ['name' => 'country', 'label' => 'Country', 'type' => 'select', 'options' => ['' => 'Any country'] + \App\Models\Registry\Startup::COUNTRIES, 'value' => old('country', $challenge->country)])
            @include('partials.field', ['name' => 'priority', 'label' => 'Priority', 'type' => 'select', 'required' => true, 'options' => config('challenges.priorities'), 'value' => old('priority', $challenge->priority ?? 'medium')])
            @include('partials.field', ['name' => 'category', 'label' => 'Category', 'type' => 'select', 'required' => true, 'options' => config('challenges.categories'), 'value' => old('category', $challenge->category ?? 'innovation')])
            @include('partials.field', ['name' => 'visibility', 'label' => 'Visibility', 'type' => 'select', 'required' => true, 'options' => \App\Models\Challenges\Challenge::VISIBILITIES, 'value' => old('visibility', $challenge->visibility ?? 'public')])
            @include('partials.field', ['name' => 'issuer_type', 'label' => 'Issuer type', 'type' => 'select', 'options' => ['' => 'Not specified'] + config('challenges.issuer_types'), 'value' => old('issuer_type', $challenge->issuer_type)])
        </div>
        <div class="form-row">
            @include('partials.field', ['name' => 'issuer_name', 'label' => 'Issuer name', 'maxlength' => 160, 'value' => old('issuer_name', $challenge->issuer_name)])
            @include('partials.field', ['name' => 'geography', 'label' => 'Geography', 'maxlength' => 200, 'value' => old('geography', $challenge->geography)])
            @include('partials.field', ['name' => 'region', 'label' => 'Region', 'maxlength' => 120, 'value' => old('region', $challenge->region)])
            @include('partials.field', ['name' => 'organization_id', 'label' => 'Organization ID (optional)', 'type' => 'number', 'min' => 1, 'value' => old('organization_id', $challenge->organization_id)])
        </div>
        @include('partials.field', ['name' => 'market_opportunity', 'label' => 'Market opportunity', 'type' => 'textarea', 'rows' => 3, 'value' => old('market_opportunity', $challenge->market_opportunity)])
        @include('partials.field', ['name' => 'existing_solutions', 'label' => 'Existing solutions', 'type' => 'textarea', 'rows' => 3, 'value' => old('existing_solutions', $challenge->existing_solutions)])
        @include('partials.field', ['name' => 'policy_considerations', 'label' => 'Policy considerations', 'type' => 'textarea', 'rows' => 3, 'value' => old('policy_considerations', $challenge->policy_considerations)])
        @include('partials.field', ['name' => 'tags', 'label' => 'Tags (comma separated)', 'value' => old('tags', implode(', ', (array) $challenge->tags))])
        @include('partials.field', ['name' => 'vgi_dimension_keys', 'label' => 'VGI dimension keys (comma separated)', 'hint' => 'Use configured dimension keys only.', 'value' => old('vgi_dimension_keys', implode(', ', (array) $challenge->vgi_dimension_keys))])
        @include('partials.field', ['name' => 'builder_step_keys', 'label' => 'Builder step keys (comma separated)', 'value' => old('builder_step_keys', implode(', ', (array) $challenge->builder_step_keys))])
        @include('partials.field', ['name' => 'expert_categories', 'label' => 'Expert categories (comma separated)', 'value' => old('expert_categories', implode(', ', (array) $challenge->expert_categories))])
        <h2>Research references</h2>
        @foreach (old('references', $challenge->research_references ?? [['title' => '', 'url' => '', 'accessed' => '']]) as $index => $reference)
            <div class="form-row">
                @include('partials.field', ['name' => "references[$index][title]", 'label' => 'Reference title', 'value' => $reference['title'] ?? ''])
                @include('partials.field', ['name' => "references[$index][url]", 'label' => 'Reference URL', 'type' => 'url', 'value' => $reference['url'] ?? ''])
                @include('partials.field', ['name' => "references[$index][accessed]", 'label' => 'Date accessed', 'type' => 'date', 'value' => $reference['accessed'] ?? ''])
            </div>
        @endforeach
        <div class="actions"><button class="btn btn-secondary" type="submit" name="action" value="save">Save draft</button>
            <button class="btn btn-primary" type="submit" name="action" value="submit">Save and submit for review</button></div>
    </form>
@endsection
