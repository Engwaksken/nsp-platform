@extends('layouts.public', ['pageTitle' => 'Share your startup profile'])

@push('head')<meta name="robots" content="noindex"><meta name="referrer" content="no-referrer">@endpush

@section('content')
    <div class="container section narrow">
        <h1>Use your Hive startup profile in {{ $client->name }}</h1>
        <p><strong>{{ $client->name }}</strong> asks to prefill your application with your startup’s profile, so you don’t have to type it again.</p>
        @include('partials.flash')

        <section class="panel" aria-labelledby="shared-heading">
            <h2 id="shared-heading">What will be shared</h2>
            <p>{{ $purpose['text'] }}</p>
            <p class="hint">You can check and edit everything in {{ $client->name }} before you submit. Your submitted application keeps a copy of what was shared at that time. You can turn sharing off later on your startup’s Data sharing page.</p>
        </section>

        @if ($memberships->isEmpty())
            <div class="alert alert-info">You are not on a startup team yet. <a href="{{ route('workspace.startups.create') }}">Register your startup</a> first, then come back to {{ $client->name }}.</div>
        @else
            <form method="post" action="{{ route('connect.decide', $client->client_id) }}">
                @csrf
                <input type="hidden" name="redirect_uri" value="{{ $redirectUri }}">
                <input type="hidden" name="state" value="{{ $state }}">
                <fieldset class="form-group">
                    <legend class="label">Which startup is applying?</legend>
                    @foreach ($memberships as $membership)
                        <label class="checkbox">
                            <input type="radio" name="startup_uuid" value="{{ $membership->startup_uuid }}" @checked($loop->first) required>
                            <span><strong>{{ $membership->startup->display_name }}</strong> ·
                                @if ($membership->sharing_on) sharing already on
                                @elseif ($membership->canManageConsent()) sharing will be turned on
                                @else sharing is off. Only an owner or founder can turn it on
                                @endif</span>
                        </label>
                    @endforeach
                    @error('startup_uuid')<p class="error">{{ $message }}</p>@enderror
                </fieldset>
                <div class="actions">
                    <button type="submit" name="decision" value="approve" class="btn btn-primary">Share and continue</button>
                    <button type="submit" name="decision" value="deny" class="btn btn-secondary" formnovalidate>Cancel</button>
                </div>
            </form>
        @endif
    </div>
@endsection
