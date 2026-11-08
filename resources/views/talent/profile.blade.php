@extends('layouts.public', ['pageTitle' => 'Candidate profile'])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="container section narrow">
        <h1>My candidate profile</h1>
        @include('partials.flash')
        <p>Use this profile to apply for roles at Hive startups. <a href="{{ route('talent.dashboard') }}">My applications</a> · <a href="{{ route('jobs.index') }}">Browse jobs</a></p>

        <form method="post" action="{{ route('talent.profile.store') }}" enctype="multipart/form-data">
            @csrf @method('PUT')
            @include('partials.field', ['name' => 'headline', 'label' => 'Headline', 'required' => true, 'maxlength' => 150, 'value' => $profile?->headline, 'hint' => 'For example: “Accountant, 3 years in SMEs” or “Final-year software engineering student”.'])
            @include('partials.field', ['name' => 'summary', 'label' => 'About you', 'type' => 'textarea', 'rows' => 5, 'value' => $profile?->summary])
            @include('partials.field', ['name' => 'skills', 'label' => 'Skills', 'value' => implode(', ', $profile?->skills ?? []), 'hint' => 'Separate with commas. Up to 15.'])
            @include('partials.checks', ['name' => 'work_types', 'label' => 'Work I am looking for', 'options' => \App\Models\Talent\Vacancy::TYPES, 'selected' => $profile?->work_types ?? []])
            <div class="grid">
                @include('partials.field', ['name' => 'location', 'label' => 'Town or city', 'maxlength' => 120, 'value' => $profile?->location])
                @include('partials.field', ['name' => 'country', 'label' => 'Country', 'type' => 'select', 'placeholder' => 'Choose…', 'value' => $profile?->country, 'options' => \App\Models\Registry\Startup::COUNTRIES])
            </div>

            <fieldset class="form-group">
                <legend class="label">Who can find this profile</legend>
                @foreach (\App\Models\Talent\TalentProfile::VISIBILITY as $value => $label)
                    <label class="checkbox"><input type="radio" name="visibility" value="{{ $value }}" @checked(old('visibility', $profile?->visibility ?? 'hidden') === $value)> {{ $label }}</label>
                @endforeach
                <p class="hint">Startups never see your email or CV in search. You can change this at any time.</p>
            </fieldset>
            <div class="form-group">
                <input type="hidden" name="open_to_work" value="0">
                <label class="checkbox"><input type="checkbox" name="open_to_work" value="1" @checked(old('open_to_work', $profile?->open_to_work ?? true))> <span>I am open to new roles now</span></label>
            </div>

            <h2 class="h3">CV</h2>
            @if ($profile?->cv_name)
                <p>Current CV: <a href="{{ route('talent.cv') }}">{{ $profile->cv_name }}</a></p>
                <label class="checkbox"><input type="checkbox" name="remove_cv" value="1"> <span>Remove my CV</span></label>
            @endif
            @include('partials.field', ['name' => 'cv', 'label' => $profile?->cv_name ? 'Replace CV' : 'Upload CV', 'type' => 'file', 'attrs' => 'accept=".pdf,.docx"', 'hint' => 'PDF or Word (.docx), up to 5 MB. Only startups you apply to can open it.'])
            <button type="submit" class="btn btn-primary">Save profile</button>
        </form>

        @if ($profile)
            <section class="panel" aria-labelledby="delete-heading">
                <h2 id="delete-heading" class="h3">Delete my candidate profile</h2>
                <p>This deletes your profile and CV. Copies sent with applications are deleted when you withdraw them or when their retention period ends.</p>
                <form method="post" action="{{ route('talent.profile.destroy') }}" data-confirm="Delete your candidate profile and CV?">
                    @csrf @method('DELETE') <button type="submit" class="btn btn-secondary">Delete profile</button>
                </form>
            </section>
        @endif
    </div>
@endsection
