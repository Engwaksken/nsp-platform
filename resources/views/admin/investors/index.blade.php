@extends('layouts.admin')
@section('title', 'Investors')

@section('content')
    <div class="page-head"><h1>Investors</h1></div>
    @include('partials.stats', ['label' => 'Summary', 'stats' => \App\Support\AdminStats::investors()])
    <p class="hint">Check who each investor is (for example their website, fund registration or references) before verifying. Verified investors can see startups that chose to be listed, and founders can ask them for introductions.</p>
    <nav class="tags" aria-label="Filter by status">
        @foreach ($statuses as $value => $label)
            <a class="tag" href="{{ route('admin.investors.index', ['status' => $value]) }}" @if ($status === $value) aria-current="true" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    @forelse ($profiles as $profile)
        <section class="panel" aria-labelledby="inv-{{ $profile->id }}">
            <h2 id="inv-{{ $profile->id }}" class="h3">{{ $profile->user?->name ?? 'Deleted user' }}@if ($profile->organisation) · {{ $profile->organisation }}@endif
                <span class="badge">{{ $statuses[$profile->status] ?? $profile->status }}</span></h2>
            <p class="meta">{{ $profile->user?->email }} · {{ \App\Models\Funding\InvestorProfile::TYPES[$profile->investor_type] ?? $profile->investor_type }} · Ticket {{ $profile->ticketLabel() }} · applied {{ $profile->created_at->format('j M Y') }}</p>
            @if ($profile->website)<p><a href="{{ $profile->website }}" rel="noopener nofollow" target="_blank">{{ $profile->website }}</a></p>@endif
            <p>{{ $profile->bio }}</p>
            @if ($profile->verification_note)<p class="meta">Last note: {{ $profile->verification_note }}</p>@endif
            <form method="post" action="{{ route('admin.investors.decide', $profile) }}">
                @csrf @method('PUT')
                @include('partials.field', ['name' => 'verification_note', 'id' => 'note-'.$profile->id, 'label' => 'Note (required when not verifying; shown to the investor)', 'type' => 'textarea', 'rows' => 2])
                <div class="inline-form">
                    @if ($profile->status !== 'verified')<button type="submit" name="status" value="verified" class="btn btn-primary btn-sm">Verify<span class="visually-hidden"> {{ $profile->user?->name }}</span></button>@endif
                    @if ($profile->status === 'pending')<button type="submit" name="status" value="rejected" class="btn btn-secondary btn-sm">Do not verify<span class="visually-hidden"> {{ $profile->user?->name }}</span></button>@endif
                    @if ($profile->status === 'verified')<button type="submit" name="status" value="suspended" class="btn btn-secondary btn-sm">Suspend<span class="visually-hidden"> {{ $profile->user?->name }}</span></button>@endif
                </div>
            </form>
        </section>
    @empty
        <p class="empty">No investors with this status.</p>
    @endforelse
    {{ $profiles->links() }}
@endsection
