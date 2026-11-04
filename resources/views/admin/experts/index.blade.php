@extends('layouts.admin')
@section('title', 'Experts')

@section('content')
    <div class="page-head">
        <h1>Experts</h1>
        <a class="btn btn-secondary" href="{{ route('admin.engagements.index') }}">Mentoring and engagements</a>
    </div>
    <p class="hint">Check each expert’s experience (website, LinkedIn or a reference) before verifying. Verified experts appear in the directory for every startup.</p>
    <nav class="tags" aria-label="Filter by status">
        @foreach ($statuses as $value => $label)
            <a class="tag" href="{{ route('admin.experts.index', ['status' => $value]) }}" @if ($status === $value) aria-current="true" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    @forelse ($profiles as $profile)
        @php($review = $reviews[$profile->user_id] ?? null)
        <section class="panel" aria-labelledby="exp-{{ $profile->id }}">
            <h2 id="exp-{{ $profile->id }}" class="h3">{{ $profile->user?->name ?? 'Deleted user' }} · {{ $profile->headline }}
                <span class="badge">{{ $statuses[$profile->status] ?? $profile->status }}</span></h2>
            <p class="meta">{{ $profile->user?->email }} · {{ \App\Models\Experts\ExpertProfile::KINDS[$profile->kind] }} · applied {{ $profile->created_at->format('j M Y') }}
                @if ($review) · {{ round($review->average, 1) }}/5 from {{ $review->total }} reviews @endif</p>
            <p>@foreach ($profile->areaLabels() as $label)<span class="tag">{{ $label }}</span>@endforeach</p>
            @if ($profile->website)<p><a href="{{ $profile->website }}" rel="noopener nofollow" target="_blank">{{ $profile->website }}</a></p>@endif
            <p>{{ $profile->bio }}</p>
            @if ($profile->verification_note)<p class="meta">Last note: {{ $profile->verification_note }}</p>@endif
            <form method="post" action="{{ route('admin.experts.decide', $profile) }}">
                @csrf @method('PUT')
                @include('partials.field', ['name' => 'verification_note', 'id' => 'note-'.$profile->id, 'label' => 'Note (required when not verifying; shown to the expert)', 'type' => 'textarea', 'rows' => 2])
                <div class="inline-form">
                    @if ($profile->status !== 'verified')<button type="submit" name="status" value="verified" class="btn btn-primary btn-sm">Verify<span class="visually-hidden"> {{ $profile->user?->name }}</span></button>@endif
                    @if ($profile->status === 'pending')<button type="submit" name="status" value="rejected" class="btn btn-secondary btn-sm">Do not verify<span class="visually-hidden"> {{ $profile->user?->name }}</span></button>@endif
                    @if ($profile->status === 'verified')<button type="submit" name="status" value="suspended" class="btn btn-secondary btn-sm">Suspend<span class="visually-hidden"> {{ $profile->user?->name }}</span></button>@endif
                </div>
            </form>
        </section>
    @empty
        <p class="empty">No experts with this status.</p>
    @endforelse
    {{ $profiles->links() }}
@endsection
