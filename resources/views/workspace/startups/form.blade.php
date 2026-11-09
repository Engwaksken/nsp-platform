@extends('layouts.workspace', ['pageTitle' => $startup->exists ? 'Edit '.$startup->display_name : 'Register a startup'])

@push('head')<meta name="robots" content="noindex">@endpush

@section('workspace')
    <div class="container section narrow">
        <h1>{{ $startup->exists ? 'Edit '.$startup->display_name : 'Register your startup' }}</h1>
        @include('partials.flash')

        @if (!empty($matches))
            <section class="alert {{ !empty($blocked) ? 'alert-danger' : 'alert-warning' }}" role="alert" aria-labelledby="matches-heading">
                <h2 id="matches-heading" class="h3">{{ !empty($blocked) ? 'This startup is already registered' : 'Is this your startup?' }}</h2>
                <p>{{ !empty($blocked)
                    ? 'A startup with the same registration number or website already has a profile. One company has one Hive profile, so ask its team to let you in.'
                    : 'A startup with the same name already has a profile. If it is yours, ask to join it. If it is a different company, confirm below.' }}</p>
                @foreach ($matches as $match)
                    <div class="panel">
                        <strong>{{ $match['startup']->display_name }}</strong> <span class="meta">({{ implode(', ', $match['evidence']) }})</span>
                        <form method="post" action="{{ route('workspace.startups.join', $match['startup']) }}" class="form-row" style="margin-top:.5rem">
                            @csrf
                            <div class="form-group"><label for="join-{{ $loop->index }}">Message to the team (optional)</label>
                                <input id="join-{{ $loop->index }}" type="text" name="message" maxlength="1000" placeholder="e.g. I am the CTO"></div>
                            <div><button type="submit" class="btn btn-primary">Ask to join<span class="visually-hidden"> {{ $match['startup']->display_name }}</span></button></div>
                        </form>
                    </div>
                @endforeach
            </section>
        @endif

        <form method="post" action="{{ $startup->exists ? route('workspace.startups.update', $startup) : route('workspace.startups.store') }}" novalidate>
            @csrf
            @if ($startup->exists) @method('PUT') @endif
            <input type="hidden" name="version" value="{{ old('version', $version) }}">
            @error('version')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror

            <section class="panel" aria-labelledby="core-heading">
                <h2 id="core-heading">Company</h2>
                <p class="hint">These details are shared across Hive (NSP and program applications) once you allow it on the Data sharing page.</p>
                @include('partials.field', ['name' => 'legal_name', 'label' => 'Registered (legal) name', 'value' => $startup->legal_name, 'required' => true, 'maxlength' => 200])
                @include('partials.field', ['name' => 'display_name', 'label' => 'Name people know you by', 'value' => $startup->display_name, 'required' => true, 'maxlength' => 120])
                <div class="form-row">
                    @include('partials.field', ['name' => 'registration_number', 'label' => 'Registration number (URSB or other)', 'value' => $startup->registration_number, 'maxlength' => 80, 'hint' => 'Leave empty if not registered yet.'])
                    @include('partials.field', ['name' => 'country', 'label' => 'Country', 'type' => 'select', 'options' => \App\Models\Registry\Startup::COUNTRIES, 'value' => $startup->country ?? 'UG', 'required' => true])
                    @include('partials.field', ['name' => 'year_founded', 'label' => 'Year founded', 'type' => 'number', 'value' => $startup->year_founded, 'attrs' => 'min="1950" max="'.now()->year.'"'])
                </div>
                @include('partials.field', ['name' => 'website', 'label' => 'Website', 'type' => 'url', 'value' => $startup->website, 'hint' => 'For example https://example.com'])
                @include('partials.field', ['name' => 'description', 'label' => 'What does your startup do?', 'type' => 'textarea', 'rows' => 4, 'value' => $startup->description])
            </section>

            <section class="panel" aria-labelledby="profile-heading">
                <h2 id="profile-heading">Profile</h2>
                <div class="form-row">
                    @include('partials.field', ['name' => 'sector', 'label' => 'Sector', 'type' => 'select', 'options' => array_combine(\App\Models\Registry\StartupProfile::SECTORS, \App\Models\Registry\StartupProfile::SECTORS), 'placeholder' => 'Choose…', 'value' => $profile->sector])
                    @include('partials.field', ['name' => 'sub_sector', 'label' => 'Sub-sector', 'value' => $profile->sub_sector, 'maxlength' => 80])
                </div>
                <div class="form-row">
                    @include('partials.field', ['name' => 'headquarters_location', 'label' => 'Where are you based?', 'value' => $profile->headquarters_location, 'maxlength' => 160, 'hint' => 'Town or district'])
                    @include('partials.field', ['name' => 'team_size', 'label' => 'Team size', 'type' => 'number', 'value' => $profile->team_size, 'attrs' => 'min="1"'])
                </div>
            </section>

            <section class="panel" aria-labelledby="private-heading">
                <h2 id="private-heading">Private details</h2>
                <p class="hint">Stored encrypted. Choose who can see each group; nothing here is ever public.</p>
                <div class="form-row">
                    @include('partials.field', ['name' => 'revenue_band', 'label' => 'Yearly revenue', 'type' => 'select', 'options' => \App\Models\Registry\StartupProfile::REVENUE_BANDS, 'placeholder' => 'Prefer not to say', 'value' => $profile->revenue_band])
                    @include('partials.field', ['name' => 'funding_raised_amount', 'label' => 'Funding raised so far', 'type' => 'number', 'value' => $profile->funding_raised_amount, 'attrs' => 'min="0" step="any"'])
                    @include('partials.field', ['name' => 'funding_currency', 'label' => 'Currency', 'type' => 'select', 'options' => ['UGX' => 'UGX', 'USD' => 'USD', 'EUR' => 'EUR', 'KES' => 'KES', 'GBP' => 'GBP'], 'placeholder' => '—', 'value' => $profile->funding_currency])
                </div>
                @include('partials.field', ['name' => 'traction_summary', 'label' => 'Traction (customers, growth, milestones)', 'type' => 'textarea', 'rows' => 3, 'value' => $profile->traction_summary])
                <div class="form-row">
                    @include('partials.field', ['name' => 'contact_email', 'label' => 'Contact email', 'type' => 'email', 'value' => $profile->contact_email])
                    @include('partials.field', ['name' => 'contact_phone', 'label' => 'Contact phone', 'value' => $profile->contact_phone, 'maxlength' => 40])
                </div>
                <fieldset class="form-group">
                    <legend class="label">Who can see these details?</legend>
                    @foreach (\App\Models\Registry\StartupProfile::VISIBILITY_GROUPS as $group => $definition)
                        @include('partials.field', ['name' => "visibility[$group]", 'id' => "visibility-$group", 'label' => $definition['label'], 'type' => 'select',
                            'options' => \App\Models\Registry\StartupProfile::VISIBILITY_LEVELS, 'value' => $profile->visibilityOf($group)])
                    @endforeach
                </fieldset>
            </section>

            @if (!empty($matches) && empty($blocked))
                <div class="form-group">
                    <label class="checkbox"><input type="checkbox" name="confirm_different" value="1" required>
                        <span>The startup shown above is a different company. Register mine as a new profile. Hive staff will double-check.</span></label>
                </div>
            @endif

            @if (empty($blocked))
                <div class="actions">
                    <button type="submit" class="btn btn-primary">{{ $startup->exists ? 'Save profile' : 'Register startup' }}</button>
                    <a class="btn btn-secondary" href="{{ $startup->exists ? route('workspace.startups.show', $startup) : route('workspace.startups.index') }}">Cancel</a>
                </div>
            @endif
        </form>
    </div>
@endsection
