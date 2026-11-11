@extends('layouts.app', ['pageTitle' => 'Expert profile'])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="container section narrow">
        <h1>Expert and mentor profile</h1>
        @include('partials.flash')
        @if ($profile)
            @php($statusText = ['pending' => 'Hive is checking your profile.', 'verified' => 'Verified by Hive. Founders can find you.', 'rejected' => 'Hive could not verify you yet. Update your profile and save to ask again.', 'suspended' => 'Your expert profile is paused. Contact Hive.'][$profile->status] ?? $profile->status)
            <div class="alert {{ $profile->status === 'verified' ? 'alert-success' : 'alert-warning' }}" role="status">
                {{ $statusText }}
                @if ($profile->verification_note && $profile->status !== 'verified') <br>Note from Hive: {{ $profile->verification_note }}@endif
            </div>
            @if ($profile->status === 'verified')<p><a class="btn btn-primary" href="{{ route('experts.dashboard') }}">Open my expert dashboard</a></p>@endif
        @else
            <p>Share your experience with Hive startups as a mentor, a consultant or both. Hive checks every profile before founders can find you.</p>
        @endif

        <form method="post" action="{{ route('experts.profile.store') }}">
            @csrf @method('PUT')
            @include('partials.field', ['name' => 'headline', 'label' => 'Headline', 'required' => true, 'maxlength' => 150, 'value' => $profile?->headline, 'hint' => 'For example: “Finance lead, 10 years in agribusiness”.'])
            @include('partials.field', ['name' => 'bio', 'label' => 'About you', 'type' => 'textarea', 'rows' => 5, 'required' => true, 'value' => $profile?->bio])
            @include('partials.field', ['name' => 'kind', 'label' => 'I offer', 'type' => 'select', 'required' => true, 'value' => $profile?->kind ?? 'both', 'options' => \App\Models\Experts\ExpertProfile::KINDS])
            @include('partials.checks', ['name' => 'expertise', 'label' => 'Expertise (choose up to 6)', 'options' => \App\Models\Experts\ExpertProfile::areas(), 'selected' => $profile?->expertise ?? []])
            @include('partials.checks', ['name' => 'sectors', 'label' => 'Sectors you know well', 'options' => array_combine(\App\Models\Registry\StartupProfile::SECTORS, \App\Models\Registry\StartupProfile::SECTORS), 'selected' => $profile?->sectors ?? []])
            @include('partials.checks', ['name' => 'languages', 'label' => 'Languages', 'options' => \App\Models\Experts\ExpertProfile::LANGUAGES, 'selected' => $profile?->languages ?? ['en']])
            @include('partials.field', ['name' => 'website', 'label' => 'Website or LinkedIn', 'type' => 'url', 'value' => $profile?->website])
            @include('partials.field', ['name' => 'availability', 'label' => 'Availability', 'maxlength' => 500, 'value' => $profile?->availability, 'hint' => 'For example: “Two hours a month, weekday evenings”.'])
            @include('partials.field', ['name' => 'rate_note', 'label' => 'Fees (optional)', 'maxlength' => 255, 'value' => $profile?->rate_note, 'hint' => 'NSP does not handle payments. Agree any fee directly with the startup.'])
            @include('partials.field', ['name' => 'max_active', 'label' => 'Most startups you can support at once', 'type' => 'number', 'required' => true, 'value' => $profile?->max_active ?? 5, 'attrs' => 'min="1" max="30" step="1"'])
            <div class="form-group">
                <input type="hidden" name="offers_free" value="0">
                <label class="checkbox"><input type="checkbox" name="offers_free" value="1" @checked(old('offers_free', $profile?->offers_free ?? true))> <span>I offer free sessions to Hive startups</span></label>
                <input type="hidden" name="is_listed" value="0">
                <label class="checkbox"><input type="checkbox" name="is_listed" value="1" @checked(old('is_listed', $profile?->is_listed ?? true))> <span>Show me in the expert directory</span></label>
            </div>
            <button type="submit" class="btn btn-primary">{{ $profile ? 'Save' : 'Send for verification' }}</button>
        </form>
    </div>
@endsection
