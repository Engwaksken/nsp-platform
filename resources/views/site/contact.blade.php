@extends('layouts.public', ['pageTitle' => 'Contact us', 'canonical' => route('contact')])

@section('content')
    <div class="container section narrow">
        <h1>Contact us</h1>
        @include('partials.flash')

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

            <button type="submit" class="btn btn-primary">Send message</button>
        </form>
    </div>
@endsection
