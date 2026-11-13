@extends('layouts.app', ['pageTitle' => 'My challenges'])

@push('head')<meta name="robots" content="noindex">@endpush

@php
    $statuses = \App\Models\Challenges\Challenge::STATUSES;
    $proposalStatuses = \App\Models\Challenges\ChallengeSolutionSubmission::STATUSES;
    $tone = fn (string $status) => match ($status) {
        'published', 'approved', 'shortlisted' => 'badge-on',
        'rejected', 'declined', 'archived' => 'badge-off',
        'changes_requested' => 'badge-new',
        default => 'badge-draft',
    };
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1>My challenges</h1>
            <p class="lead-muted">Challenges you submitted, proposals you or your startup sent, and challenges you saved.</p>
        </div>
        <div class="actions">
            <a class="btn btn-secondary" href="{{ route('challenges.index') }}">Browse challenges</a>
            <a class="btn btn-primary" href="{{ route('challenges.create') }}"><i class="fas fa-plus" aria-hidden="true"></i> Submit a challenge</a>
        </div>
    </div>

    @include('partials.flash')

    <section class="panel" aria-labelledby="submitted-heading">
        <h2 id="submitted-heading">Challenges I submitted <span class="count">{{ $challenges->count() }}</span></h2>
        @if ($challenges->isEmpty())
            <div class="empty-state">
                <i class="fas fa-lightbulb" aria-hidden="true"></i>
                <p>You have not submitted a challenge yet. Describe a real problem your community, sector or organisation faces, and startups can propose solutions once Hive reviewers publish it.</p>
                <a class="btn btn-primary btn-sm" href="{{ route('challenges.create') }}">Submit a challenge</a>
            </div>
        @else
            <div class="table-wrap">
                <table>
                    <thead><tr><th scope="col">Challenge</th><th scope="col">Status</th><th scope="col">Last change</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
                    <tbody>
                    @foreach ($challenges as $challenge)
                        @php($review = $challenge->reviews->sortByDesc('created_at')->first())
                        <tr>
                            <td>
                                <strong>{{ $challenge->title ?: 'Untitled draft' }}</strong>
                                @if ($challenge->published_version_id && $challenge->status !== 'published')
                                    <br><span class="meta">A published version is online while this revision is in progress.</span>
                                @endif
                                @if ($review && in_array($challenge->status, ['changes_requested', 'rejected'], true) && ($review->comments || $review->reason))
                                    <p class="review-note"><strong>Reviewer feedback:</strong>
                                        @if ($review->reason)<em>{{ config('challenges.rejection_reasons')[$review->reason] ?? $review->reason }}.</em>@endif
                                        {{ $review->comments }}</p>
                                @endif
                            </td>
                            <td><span class="badge {{ $tone($challenge->status) }}">{{ $statuses[$challenge->status] ?? $challenge->status }}</span></td>
                            <td><time datetime="{{ $challenge->updated_at->toIso8601String() }}">{{ $challenge->updated_at->format('j M Y') }}</time></td>
                            <td class="row-actions">
                                @if ($challenge->publishedVersion && $challenge->isVisibleTo(auth()->user()))
                                    <a class="btn btn-icon" href="{{ route('challenges.show', $challenge) }}" data-tip="View"><i class="fas fa-eye" aria-hidden="true"></i><span class="visually-hidden">View {{ $challenge->title }}</span></a>
                                @endif
                                @if ($challenge->isEditable())
                                    <a class="btn btn-icon" href="{{ route('challenges.edit', $challenge) }}" data-tip="{{ $challenge->published_version_id ? 'Revise' : 'Edit' }}"><i class="fas fa-pen-to-square" aria-hidden="true"></i><span class="visually-hidden">{{ $challenge->published_version_id ? 'Revise' : 'Edit' }} {{ $challenge->title }}</span></a>
                                @endif
                                @if (in_array($challenge->status, ['draft', 'changes_requested'], true))
                                    <form method="post" action="{{ route('challenges.submit', $challenge) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="btn btn-icon btn-icon-primary" data-tip="Submit for review"><i class="fas fa-paper-plane" aria-hidden="true"></i><span class="visually-hidden">Submit {{ $challenge->title }} for review</span></button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="panel" aria-labelledby="proposals-heading">
        <h2 id="proposals-heading">My proposals <span class="count">{{ $proposals->count() }}</span></h2>
        @if ($proposals->isEmpty())
            <div class="empty-state">
                <i class="fas fa-handshake" aria-hidden="true"></i>
                <p>No proposals yet. Open a published challenge and propose how you or your startup would solve it.</p>
                <a class="btn btn-secondary btn-sm" href="{{ route('challenges.index') }}">Find a challenge</a>
            </div>
        @else
            <ul class="list-rows">
                @foreach ($proposals as $proposal)
                    <li>
                        <div>
                            <a href="{{ route('challenges.proposals.show', $proposal) }}"><strong>{{ $proposal->challenge?->publishedVersion?->title ?? 'Challenge no longer published' }}</strong></a>
                            <p class="meta">
                                @if ($proposal->startup){{ $proposal->startup->display_name }} · @endif
                                @if ($proposal->user && $proposal->user->id !== auth()->id())by {{ $proposal->user->name }} · @endif
                                sent {{ $proposal->created_at->format('j M Y') }}
                            </p>
                        </div>
                        <span class="badge {{ $tone($proposal->status) }}">{{ $proposalStatuses[$proposal->status] ?? $proposal->status }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="panel" aria-labelledby="saved-heading">
        <h2 id="saved-heading">Saved challenges <span class="count">{{ $bookmarks->count() }}</span></h2>
        @if ($bookmarks->isEmpty())
            <p class="hint">Challenges you save while browsing appear here.</p>
        @else
            <ul class="list-rows">
                @foreach ($bookmarks as $bookmark)
                    <li>
                        <div>
                            <a href="{{ route('challenges.show', $bookmark->challenge) }}"><strong>{{ $bookmark->challenge->publishedVersion->title }}</strong></a>
                            <p class="meta">{{ config('challenges.categories')[$bookmark->challenge->publishedVersion->category] ?? '' }} · saved {{ $bookmark->created_at->format('j M Y') }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
