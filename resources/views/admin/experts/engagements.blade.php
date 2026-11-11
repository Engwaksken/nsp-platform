@extends('layouts.admin')
@section('title', 'Mentoring and engagements')

@section('content')
    <div class="page-head"><h1>Mentoring and engagements</h1></div>
    @include('partials.flash')

    <section class="panel" aria-labelledby="assign-heading">
        <h2 id="assign-heading" class="h3">Assign a mentor</h2>
        @if ($mentors)
            <form method="post" action="{{ route('admin.engagements.assign') }}">
                @csrf
                <div class="grid">
                    @include('partials.field', ['name' => 'startup_uuid', 'label' => 'Startup', 'type' => 'select', 'required' => true, 'placeholder' => 'Choose…', 'options' => $startups])
                    @include('partials.field', ['name' => 'expert_user_id', 'label' => 'Mentor', 'type' => 'select', 'required' => true, 'placeholder' => 'Choose…', 'options' => $mentors])
                </div>
                @include('partials.field', ['name' => 'topic', 'label' => 'Mentorship focus', 'required' => true, 'maxlength' => 150])
                @include('partials.field', ['name' => 'details', 'label' => 'Notes for both (optional)', 'type' => 'textarea', 'rows' => 2])
                <button type="submit" class="btn btn-primary">Assign mentor</button>
            </form>
        @else
            <p class="empty">No verified mentors yet. <a href="{{ route('admin.experts.index') }}">Verify experts</a> first.</p>
        @endif
    </section>

    <nav class="tags" aria-label="Filter by status">
        @foreach (\App\Models\Experts\Engagement::STATUSES as $value => $label)
            <a class="tag" href="{{ route('admin.engagements.index', ['status' => $value]) }}" @if ($status === $value) aria-current="true" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <p class="hint">Session notes are private to the startup and the expert and are not shown here.</p>
    <div class="table-wrap" tabindex="0" role="region" aria-label="Engagements">
        <table>
            <thead><tr><th scope="col">Startup</th><th scope="col">Expert</th><th scope="col">Topic</th><th scope="col">Type</th><th scope="col">Sessions</th><th scope="col">Feedback</th><th scope="col">Started</th></tr></thead>
            <tbody>
            @forelse ($engagements as $engagement)
                <tr>
                    <td>@if ($engagement->startup){{ $engagement->startup->display_name }}@else<span class="meta">Unknown</span>@endif</td>
                    <td>@if ($engagement->expert){{ $engagement->expert->name }}@else<span class="meta">Unknown</span>@endif</td>
                    <td>{{ $engagement->topic }}</td>
                    <td>{{ \App\Models\Experts\Engagement::TYPES[$engagement->type] }}@if ($engagement->source === 'staff_assignment') (assigned)@endif</td>
                    <td>{{ $engagement->sessions_count }}</td>
                    <td>@if ($engagement->review){{ $engagement->review->rating }}/5 @else<span class="meta">Not reviewed</span>@endif @if ($engagement->review?->comment)<br><span class="meta">{{ \Illuminate\Support\Str::limit($engagement->review->comment, 120) }}</span>@endif</td>
                    <td>{{ $engagement->created_at->format('j M Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">No engagements with this status.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $engagements->links() }}
@endsection
