@extends('layouts.admin')
@section('title', 'Challenge proposals')

@section('content')
    <div class="page-head"><h1>Proposals · {{ $challenge->title }}</h1><a class="btn btn-secondary" href="{{ route('admin.challenges.show', $challenge) }}">Back to challenge</a></div>
    @include('partials.flash')
    @forelse ($proposals as $proposal)
        <article class="panel"><h2>{{ $proposal->user?->name ?? 'Former user' }} · {{ \Illuminate\Support\Str::headline($proposal->status) }}</h2>
            <p>{{ $proposal->summary }}</p>@if ($proposal->approach)<p>{{ $proposal->approach }}</p>@endif
            <p class="meta">{{ $proposal->startup?->display_name ?? 'No startup linked' }} · {{ $proposal->created_at->format('j M Y') }}</p>
            <form method="post" action="{{ route('admin.challenges.proposals.update', [$challenge, $proposal]) }}" class="actions">@csrf @method('PUT')
                <label for="proposal-status-{{ $proposal->id }}">Status</label><select id="proposal-status-{{ $proposal->id }}" name="status">@foreach (\App\Models\Challenges\ChallengeSolutionSubmission::STATUSES as $key => $label)<option value="{{ $key }}" @selected($proposal->status === $key)>{{ $label }}</option>@endforeach</select>
                <button class="btn btn-secondary" type="submit">Update</button>
            </form>
        </article>
    @empty<p class="empty">No proposals have been submitted.</p>@endforelse
@endsection
