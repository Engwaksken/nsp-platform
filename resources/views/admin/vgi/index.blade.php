@extends('layouts.admin')
@section('title', 'Growth assessments')

@section('content')
    <div class="page-head">
        <h1>Growth assessments</h1>
        @can('edit-vgi-frameworks')
            <form method="post" action="{{ route('admin.vgi.create') }}">
                @csrf
                <button type="submit" class="btn btn-primary">New draft version</button>
            </form>
        @endcan
    </div>
    @error('approve')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror

    @if (!$current)
        <div class="alert alert-warning">No rubric is approved yet, so founders cannot take the assessment. Review the draft, edit it as needed, and have another administrator approve it.</div>
    @else
        <p>Founders use <strong>{{ $current->name }} v{{ $current->version }}</strong>, approved {{ $current->approved_at->format('j M Y') }} by {{ $current->approver?->name ?? 'an unknown user' }}.</p>
    @endif

    <div data-tabs="Growth assessments" data-tabs-key="vgi-index">
    <section class="panel" data-tab="Rubric versions" data-tab-count="{{ count($frameworks) }}" aria-labelledby="versions-heading">
        <h2 id="versions-heading">Rubric versions</h2>
        <div class="table-wrap" tabindex="0" role="region" aria-label="Rubric versions">
            <table>
                <thead><tr><th scope="col">Version</th><th scope="col">Status</th><th scope="col">Questions</th><th scope="col">Last edited by</th><th scope="col">Approved</th><th scope="col">Actions</th></tr></thead>
                <tbody>
                @foreach ($frameworks as $framework)
                    <tr>
                        <td>{{ $framework->name }} v{{ $framework->version }}</td>
                        <td><span class="badge badge-{{ ['approved' => 'on', 'draft' => 'draft'][$framework->status] ?? 'off' }}">{{ ucfirst($framework->status) }}</span></td>
                        <td>{{ $framework->questionCount() }}</td>
                        <td>{{ $framework->editor?->name ?? 'Seeded starter draft' }}</td>
                        <td>@if ($framework->approved_at){{ $framework->approved_at->format('j M Y') }}@else<span class="meta">Not approved</span>@endif @if ($framework->approval_note)<br><span class="meta">{{ $framework->approval_note }}</span>@endif</td>
                        <td><a class="btn btn-secondary btn-sm" href="{{ route('admin.vgi.edit', $framework) }}" data-modal="lg">{{ $framework->status === 'draft' ? 'Review and edit' : 'View' }}</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @if ($assessments)
        <section class="panel" data-tab="Submitted assessments" data-tab-count="{{ $assessments->total() }}" aria-labelledby="assessments-heading">
            <h2 id="assessments-heading">Submitted assessments</h2>
            <div class="table-wrap" tabindex="0" role="region" aria-label="Submitted assessments">
                <table>
                    <thead><tr><th scope="col">Startup</th><th scope="col">Date</th><th scope="col">Score</th><th scope="col">Stage</th><th scope="col">Rubric</th></tr></thead>
                    <tbody>
                    @forelse ($assessments as $assessment)
                        <tr>
                            <td><a href="{{ route('admin.vgi.assessment', $assessment) }}">{{ $assessment->startup?->display_name }}</a></td>
                            <td>{{ $assessment->submitted_at->format('j M Y') }}</td>
                            <td>{{ number_format($assessment->displayScore(), 1) }}@if ($assessment->override_at) <span class="badge">adjusted</span>@endif</td>
                            <td>{{ $assessment->displayBand() }}</td>
                            <td>v{{ $assessment->framework->version }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty">None yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            {{ $assessments->links() }}
        </section>
    @endif
    </div>
@endsection
