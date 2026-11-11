@extends('layouts.app', ['pageTitle' => 'Challenge proposal', 'metaDescription' => 'Private proposal details for the Challenge Exchange.'])

@section('content')
    <article class="container section narrow">
        <p class="meta"><a href="{{ route('challenges.show', $proposal->challenge) }}">{{ $proposal->challenge->publishedVersion?->title ?? 'Challenge' }}</a> · Private proposal</p>
        <h1>Your proposal</h1>
        @include('partials.flash')
        <p class="alert alert-info">Visible only to you, active members of the linked startup team, and Hive challenge reviewers.</p>
        <dl>
            <dt>Status</dt><dd>{{ \App\Models\Challenges\ChallengeSolutionSubmission::STATUSES[$proposal->status] ?? $proposal->status }}</dd>
            @if ($proposal->startup)<dt>Startup</dt><dd>{{ $proposal->startup->display_name }}</dd>@endif
            <dt>Summary</dt><dd>{{ $proposal->summary }}</dd>
            @if ($proposal->approach)<dt>Approach</dt><dd>{{ $proposal->approach }}</dd>@endif
            <dt>Submitted</dt><dd>{{ $proposal->created_at->format('j M Y') }}</dd>
        </dl>
        <p><a class="btn btn-secondary" href="{{ route('challenges.mine') }}">My challenges and proposals</a></p>
    </article>
@endsection
