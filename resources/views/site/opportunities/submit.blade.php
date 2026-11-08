@extends('layouts.public', ['pageTitle' => 'Share an opportunity'])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="container section narrow">
        <h1>Share an opportunity</h1>
        <p>Know of a grant, competition, event or call that startups should hear about? Hive staff check every submission before it is published.</p>
        @include('partials.flash')
        <form method="post" action="{{ route('opportunities.store') }}" novalidate>
            @csrf
            @include('site.opportunities.fields', ['staff' => false])
            <button type="submit" class="btn btn-primary">Send for review</button>
        </form>
    </div>
@endsection
