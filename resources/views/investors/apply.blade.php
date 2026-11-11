@extends('layouts.app', ['pageTitle' => 'Investor profile'])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="container section narrow">
        <h1>Investor profile</h1>
        @include('partials.flash')
        @if ($profile)
            @php($statusText = ['pending' => 'Hive is checking your details.', 'verified' => 'Verified by Hive.', 'rejected' => 'Hive could not verify you yet. Update your details and save to ask again.', 'suspended' => 'Your investor access is paused. Contact Hive.'][$profile->status] ?? $profile->status)
            <div class="alert {{ $profile->status === 'verified' ? 'alert-success' : 'alert-warning' }}" role="status">
                {{ $statusText }}
                @if ($profile->verification_note && $profile->status !== 'verified') <br>Note from Hive: {{ $profile->verification_note }}@endif
            </div>
            @if ($profile->status === 'verified')<p><a class="btn btn-primary" href="{{ route('investor.dashboard') }}">Open investor dashboard</a></p>@endif
        @else
            <p>Tell us about you as an investor. Hive checks every investor before they can see any startup. Startups choose whether to be listed and decide who sees their documents.</p>
        @endif

        <form method="post" action="{{ route('investor.apply.store') }}">
            @csrf @method('PUT')
            @include('partials.field', ['name' => 'organisation', 'label' => 'Organisation or fund (leave blank if you invest personally)', 'value' => $profile?->organisation, 'maxlength' => 150])
            @include('partials.field', ['name' => 'investor_type', 'label' => 'Investor type', 'type' => 'select', 'required' => true, 'value' => $profile?->investor_type, 'placeholder' => 'Choose…', 'options' => \App\Models\Funding\InvestorProfile::TYPES])
            @include('partials.field', ['name' => 'bio', 'label' => 'About you', 'type' => 'textarea', 'rows' => 4, 'required' => true, 'value' => $profile?->bio, 'hint' => 'Your experience and past investments. Hive uses this to verify you.'])
            @include('partials.field', ['name' => 'website', 'label' => 'Website or LinkedIn', 'type' => 'url', 'value' => $profile?->website])
            @include('partials.field', ['name' => 'thesis', 'label' => 'What you invest in', 'type' => 'textarea', 'rows' => 3, 'value' => $profile?->thesis, 'hint' => 'Founders see this.'])
            <div class="grid">
                @include('partials.field', ['name' => 'currency', 'label' => 'Currency', 'type' => 'select', 'required' => true, 'value' => $profile?->currency ?? 'USD', 'options' => array_combine(\App\Models\Funding\InvestorProfile::CURRENCIES, \App\Models\Funding\InvestorProfile::CURRENCIES)])
                @include('partials.field', ['name' => 'ticket_min', 'label' => 'Smallest ticket', 'type' => 'number', 'value' => $profile?->ticket_min, 'attrs' => 'min="0" step="1" inputmode="numeric"'])
                @include('partials.field', ['name' => 'ticket_max', 'label' => 'Largest ticket', 'type' => 'number', 'value' => $profile?->ticket_max, 'attrs' => 'min="0" step="1" inputmode="numeric"'])
            </div>
            @include('partials.checks', ['name' => 'instruments', 'label' => 'Types of funding you provide', 'options' => \App\Models\Funding\InvestorProfile::INSTRUMENTS, 'selected' => $profile?->instruments ?? []])
            @include('partials.checks', ['name' => 'sectors', 'label' => 'Sectors (leave all unticked for any)', 'options' => array_combine(\App\Models\Registry\StartupProfile::SECTORS, \App\Models\Registry\StartupProfile::SECTORS), 'selected' => $profile?->sectors ?? []])
            @include('partials.checks', ['name' => 'countries', 'label' => 'Countries (leave all unticked for any)', 'options' => \App\Models\Registry\Startup::COUNTRIES, 'selected' => $profile?->countries ?? []])
            @include('partials.checks', ['name' => 'stages', 'label' => 'Growth stages (leave all unticked for any)', 'options' => array_combine($stageOptions, $stageOptions), 'selected' => $profile?->stages ?? [], 'emptyText' => 'Growth stages appear here once Hive approves its growth assessment.'])
            <div class="form-group">
                <input type="hidden" name="is_listed" value="0">
                <label class="checkbox"><input type="checkbox" name="is_listed" value="1" @checked(old('is_listed', $profile?->is_listed ?? true))>
                    <span>Let founders with an open funding request see my profile and ask for an introduction</span></label>
            </div>
            <button type="submit" class="btn btn-primary">{{ $profile ? 'Save' : 'Send for verification' }}</button>
        </form>
    </div>
@endsection
