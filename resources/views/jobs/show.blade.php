@extends('layouts.public', ['pageTitle' => $vacancy->title.' · '.$vacancy->startup->display_name, 'metaDescription' => \Illuminate\Support\Str::limit($vacancy->description, 150)])

@section('content')
    <div class="container section narrow">
        <p><a href="{{ route('jobs.index') }}">← All jobs</a></p>
        <h1>{{ $vacancy->title }}</h1>
        @include('partials.flash')
        <p class="meta">{{ $vacancy->startup->display_name }}@if ($vacancy->startup->profile?->sector) · {{ $vacancy->startup->profile->sector }}@endif
            · {{ \App\Models\Talent\Vacancy::TYPES[$vacancy->type] }}
            · {{ $vacancy->location ?: (\App\Models\Registry\Startup::COUNTRIES[$vacancy->startup->country] ?? '') }}@if ($vacancy->remote) · Remote possible @endif
            @if ($vacancy->pay_note) · {{ $vacancy->pay_note }}@endif</p>
        <p class="meta">@if ($vacancy->closes_on)Apply by {{ $vacancy->closes_on->format('j F Y') }}@else Open until filled @endif</p>

        <h2>About the role</h2>
        <p>{!! nl2br(e($vacancy->description)) !!}</p>
        @if ($vacancy->requirements)<h2>What you need</h2><p>{!! nl2br(e($vacancy->requirements)) !!}</p>@endif
        @if ($vacancy->skills)<p>@foreach ($vacancy->skills as $skill)<span class="tag">{{ $skill }}</span>@endforeach</p>@endif

        <section class="panel" aria-labelledby="apply-heading">
            <h2 id="apply-heading">Apply</h2>
            @guest
                <p><a class="btn btn-primary" href="{{ route('login') }}">Sign in to apply</a></p>
            @else
                @if ($application)
                    <p>You applied on {{ $application->created_at->format('j M Y') }}. Status: <strong>{{ \App\Models\Talent\JobApplication::CANDIDATE_LABELS[$application->status] }}</strong>. <a href="{{ route('talent.dashboard') }}">My applications</a></p>
                @elseif (! $vacancy->isAcceptingApplications())
                    <p>This role is no longer taking applications.</p>
                @elseif (! $profile)
                    <p>Create your candidate profile first. It takes a few minutes and you can reuse it for every application.</p>
                    <p><a class="btn btn-primary" href="{{ route('talent.profile') }}">Create my candidate profile</a></p>
                @else
                    @if ($invitation)<p class="alert alert-success" role="status">{{ $vacancy->startup->display_name }} invited you to apply.@if ($invitation->message) “{{ $invitation->message }}”@endif</p>@endif
                    <form method="post" action="{{ route('jobs.apply', $vacancy) }}">
                        @csrf
                        @include('partials.field', ['name' => 'cover_note', 'label' => 'Why you are interested (optional)', 'type' => 'textarea', 'rows' => 5])
                        <p class="hint">Your profile: <strong>{{ $profile->headline }}</strong>@if ($profile->cv_name) · CV: {{ $profile->cv_name }}@else · no CV attached @endif. <a href="{{ route('talent.profile') }}">Update it</a></p>
                        <div class="form-group">
                            <label class="checkbox"><input type="checkbox" name="consent" value="1" required>
                                <span>I agree to share my name, email, candidate profile, CV and this note with {{ $vacancy->startup->display_name }}’s hiring team for this role. A copy of my CV is kept for {{ config('nsp.talent.cv_retention_days') }} days after the role or my application closes, or until I withdraw.</span></label>
                            @error('consent')<p class="error">{{ $message }}</p>@enderror
                        </div>
                        <button type="submit" class="btn btn-primary">Send application</button>
                    </form>
                @endif
            @endguest
        </section>
    </div>
@endsection
