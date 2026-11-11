@extends('layouts.public', ['pageTitle' => 'Contact us', 'canonical' => route('contact')])

@section('content')
    @php
        $email = $brand['contact_email'] ?? null;
        $phone = $brand['contact_phone'] ?? null;
        $address = $brand['contact_address'] ?? null;
        $map = $brand['contact_map_url'] ?? null;
        $social = \App\Support\Settings::json('social_links');
        $socialIcons = ['linkedin' => 'fa-briefcase', 'x' => 'fa-hashtag', 'facebook' => 'fa-thumbs-up', 'instagram' => 'fa-camera', 'youtube' => 'fa-circle-play'];
        $socialNames = \App\Http\Controllers\Admin\SettingsController::SOCIAL;
    @endphp
    <div class="container section">
        <header class="contact-head">
            <h1>Contact us</h1>
            <p class="lead-muted">Questions about {{ $brand['site_name'] }}, a programme or your account? Send us a message and the right team will reply.</p>
        </header>
        @include('partials.flash')

        <div class="contact-layout">
            <section class="contact-form-card" aria-labelledby="form-heading">
                <h2 id="form-heading"><i class="fas fa-paper-plane" aria-hidden="true"></i> Send a message</h2>
                <form method="post" action="{{ route('contact.store') }}" novalidate>
                    @csrf
                    @include('partials.field', ['name' => 'topic', 'label' => 'What is this about?', 'type' => 'select', 'options' => $topics, 'value' => 'general', 'required' => true])
                    <div class="form-row">
                        @include('partials.field', ['name' => 'name', 'label' => 'Your name', 'required' => true, 'autocomplete' => 'name', 'maxlength' => 120])
                        @include('partials.field', ['name' => 'email', 'label' => 'Email address', 'type' => 'email', 'required' => true, 'autocomplete' => 'email', 'maxlength' => 255])
                    </div>
                    @include('partials.field', ['name' => 'subject', 'label' => 'Subject', 'required' => true, 'maxlength' => 200])
                    @include('partials.field', ['name' => 'message', 'label' => 'Message', 'type' => 'textarea', 'rows' => 7, 'required' => true])

                    <div class="hp-field" aria-hidden="true">
                        <label for="website">Leave this field empty</label>
                        <input id="website" type="text" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="form-group">
                        <label class="checkbox">
                            <input type="checkbox" name="consent" value="1" @checked(old('consent')) required
                                   @error('consent') aria-invalid="true" aria-describedby="consent-error" @enderror>
                            <span>I agree that {{ $brand['site_name'] }} may store my name, email and message to reply to me.
                                We do not use these details for marketing. You can ask us to delete them at any time.</span>
                        </label>
                        @error('consent')<p class="error" id="consent-error">{{ $message }}</p>@enderror
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane" aria-hidden="true"></i> Send message</button>
                </form>
            </section>

            <aside class="contact-aside" aria-label="Contact details">
                <section class="contact-details-card" aria-labelledby="details-heading">
                    <h2 id="details-heading">Get in touch</h2>
                    @if ($email || $phone || $address)
                        <ul class="contact-details">
                            @if ($email)
                                <li><span class="contact-icon" aria-hidden="true"><i class="fas fa-envelope"></i></span>
                                    <span><span class="contact-label">Email</span><a href="mailto:{{ $email }}">{{ $email }}</a></span></li>
                            @endif
                            @if ($phone)
                                <li><span class="contact-icon" aria-hidden="true"><i class="fas fa-phone"></i></span>
                                    <span><span class="contact-label">Phone</span><a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}">{{ $phone }}</a></span></li>
                            @endif
                            @if ($address)
                                <li><span class="contact-icon" aria-hidden="true"><i class="fas fa-location-dot"></i></span>
                                    <span><span class="contact-label">Address</span>{{ $address }}</span></li>
                            @endif
                        </ul>
                    @else
                        <p class="hint">Use the form and we will reply by email.</p>
                    @endif
                    @if ($social)
                        <div class="contact-social">
                            <span class="contact-label">Follow us</span>
                            <ul>
                                @foreach ($social as $network => $url)
                                    <li><a href="{{ $url }}" rel="noopener" target="_blank"><i class="fas {{ $socialIcons[$network] ?? 'fa-link' }}" aria-hidden="true"></i> {{ $socialNames[$network] ?? ucfirst($network) }}<span class="visually-hidden"> (opens in a new tab)</span></a></li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </section>

                @if ($map)
                    <section class="contact-map-card" aria-labelledby="map-heading">
                        <h2 id="map-heading" class="visually-hidden">Location map</h2>
                        <div class="contact-map">
                            <iframe src="{{ $map }}" title="Map showing where to find {{ $brand['site_name'] }}" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
                        </div>
                    </section>
                @endif
            </aside>
        </div>
    </div>
@endsection
