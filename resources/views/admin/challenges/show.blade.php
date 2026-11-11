@extends('layouts.admin')
@section('title', 'Review challenge')

@section('content')
    <div class="page-head"><h1>{{ $challenge->title }}</h1>
        @can('manage-challenges')<a class="btn btn-secondary" href="{{ route('admin.challenges.edit', $challenge) }}">Edit working copy</a>@endcan
    </div>
    @include('partials.flash')
    <p>Status: <strong>{{ \Illuminate\Support\Str::headline($challenge->status) }}</strong> · current version @if ($challenge->current_version){{ $challenge->current_version }}@else<span class="meta">None</span>@endif</p>
    @if ($challenge->publishedVersion)<p>Published version {{ $challenge->publishedVersion->version }} remains public until a replacement version is approved and published.</p>@endif
    @if ($to)
        <section class="panel"><h2>Latest submitted snapshot: {{ $to->title }}</h2><p>{{ $to->problem_description }}</p>
            <p>Visibility: {{ \Illuminate\Support\Str::headline($to->visibility) }} · Sector: @if ($to->sector){{ $to->sector }}@else<span class="meta">Not given</span>@endif</p>
            @if ($to->field('research_references'))<h3>Submitted references</h3><ul>@foreach ($to->field('research_references') as $reference)<li>{{ $reference['title'] ?? 'Source' }} @if (!empty($reference['url']))<a href="{{ $reference['url'] }}" rel="noopener nofollow">Open source</a>@endif</li>@endforeach</ul>@endif
            @if ($workingDiffers)<p class="hint">The working copy differs from this submitted snapshot. Only this snapshot can be reviewed.</p>@endif
        </section>
    @endif
    @if ($from && $to)<section class="panel"><h2>Version changes</h2><dl>@foreach ($diff as $field => $change)<dt>{{ \Illuminate\Support\Str::headline($field) }}</dt><dd><del>@if ($change['from']){{ $change['from'] }}@else<span class="meta">Empty</span>@endif</del> <i class="fas fa-arrow-right" aria-hidden="true"></i><span class="visually-hidden">to</span> <ins>@if ($change['to']){{ $change['to'] }}@else<span class="meta">Empty</span>@endif</ins></dd>@endforeach</dl></section>@endif
    <section class="panel"><h2>Review history</h2>
        @forelse ($challenge->reviews as $review)<article><p><strong>{{ \App\Models\Challenges\ChallengeReview::DECISIONS[$review->decision] ?? $review->decision }}</strong> by {{ $review->reviewer?->name ?? 'Former reviewer' }} · {{ $review->created_at->format('j M Y H:i') }}</p>@if ($review->comments)<p>{{ $review->comments }}</p>@endif @if ($review->source_validation_notes)<p class="meta">Source notes: {{ $review->source_validation_notes }}</p>@endif</article>
        @empty<p>No review decisions yet.</p>@endforelse
    </section>
    @can('review-challenges')
        @if (in_array($challenge->status, ['submitted', 'under_review'], true))
            <form method="post" action="{{ route('admin.challenges.review', $challenge) }}" class="panel">
                @csrf<h2>Record review</h2>
                @include('partials.field', ['name' => 'comments', 'label' => 'Reviewer comments', 'type' => 'textarea', 'rows' => 3])
                @include('partials.field', ['name' => 'source_validation_notes', 'label' => 'Source validation notes (internal)', 'type' => 'textarea', 'rows' => 2])
                @include('partials.field', ['name' => 'reason', 'label' => 'Rejection reason', 'type' => 'select', 'options' => ['' => 'Choose when rejecting'] + config('challenges.rejection_reasons')])
                <div class="actions"><button class="btn btn-primary" name="decision" value="approve">Approve</button><button class="btn btn-secondary" name="decision" value="request_changes">Request changes</button><button class="btn btn-danger" name="decision" value="reject">Reject</button></div>
            </form>
        @endif
        @if ($challenge->status === 'approved')<form method="post" action="{{ route('admin.challenges.publish', $challenge) }}" class="panel">@csrf<h2>Publish approved version</h2><p>Only the approved snapshot will become public.</p><button class="btn btn-primary">Publish</button></form>@endif
        @if ($challenge->status !== 'archived')<form method="post" action="{{ route('admin.challenges.archive', $challenge) }}" class="panel" data-confirm="Archive this challenge and remove it from public search?">@csrf<button class="btn btn-secondary">Archive</button></form>@endif
    @endcan
    <section class="panel"><h2>Versions</h2><ol>@foreach ($challenge->versions as $version)<li>v{{ $version->version }}: {{ $version->title }} · submitted {{ $version->created_at->format('j M Y') }} @if ($version->published_at)· published {{ $version->published_at->format('j M Y') }}@endif</li>@endforeach</ol></section>
    @can('review-challenges')<p><a href="{{ route('admin.challenges.proposals', $challenge) }}">View proposals ({{ $proposalCount }})</a></p>@endcan
@endsection
