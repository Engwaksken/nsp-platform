@extends('layouts.app', ['pageTitle' => $challenge->exists ? 'Edit challenge' : 'Submit a challenge'])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="page-head">
        <div>
            <p class="meta"><a href="{{ route('challenges.mine') }}">My challenges</a></p>
            <h1>{{ $challenge->exists ? ($challenge->title ?: 'Edit challenge') : 'Submit a challenge' }}</h1>
            @if ($challenge->exists)
                <p><span class="badge badge-draft">{{ \App\Models\Challenges\Challenge::STATUSES[$challenge->status] ?? $challenge->status }}</span></p>
            @endif
        </div>
    </div>

    @include('partials.flash')

    @if (! $challenge->exists)
        <ol class="steps-hint">
            <li><strong>Describe the problem</strong> and save it as a draft.</li>
            <li><strong>Add supporting evidence</strong>: studies, statistics or documents.</li>
            <li><strong>Submit for review.</strong> Hive reviewers check it before anything is published.</li>
        </ol>
    @elseif ($challenge->published_version_id)
        <p class="alert alert-info">You are editing a new revision. The published version stays online until reviewers publish this one.</p>
    @endif

    @if ($challenge->exists && ! $challenge->isEditable())
        <p class="alert alert-info">This challenge is with Hive reviewers, so it cannot be changed right now. You will be notified when they decide.</p>
    @else
        <form method="post" action="{{ $challenge->exists ? route('challenges.update', $challenge) : route('challenges.store') }}" class="panel">
            @csrf
            @if ($challenge->exists) @method('PUT') @endif
            @include('challenges.fields', ['challenge' => $challenge, 'staff' => false])
            <div class="actions">
                <button class="btn btn-secondary" type="submit" name="action" value="save">Save draft</button>
                <button class="btn btn-primary" type="submit" name="action" value="submit">Save and submit for review</button>
            </div>
        </form>
    @endif

    @if ($challenge->exists)
        @include('challenges.evidence', [
            'challenge' => $challenge,
            'storeRoute' => 'challenges.evidence.store',
            'destroyRoute' => 'challenges.evidence.destroy',
        ])
    @else
        <p class="hint">You can add supporting evidence after you save the draft.</p>
    @endif
@endsection
