@extends('layouts.workspace', ['pageTitle' => 'Applications and programs · '.$startup->display_name])

@push('head')<meta name="robots" content="noindex">@endpush

@section('workspace')
    <div class="container section">
        <h1>Applications and programs · {{ $startup->display_name }}</h1>
        <p class="hint">Hive IMS sends these updates when you apply with your Hive startup profile. Reviews and scores stay in IMS.
            @if ($imsUrl) <a href="{{ $imsUrl }}" rel="noopener" target="_blank">Open Hive IMS</a> for full details.@endif</p>

        <h2>My programs</h2>
        @forelse ($programs as $program)
            <div class="panel">
                <strong>{{ $program->program_title ?: 'Hive program' }}</strong>@if ($program->cohort_name) · {{ $program->cohort_name }}@endif
                <p class="meta">@if ($program->program_start_date){{ $program->program_start_date->format('j M Y') }} – {{ $program->program_end_date?->format('j M Y') ?? 'ongoing' }}@else Dates to be confirmed @endif</p>
            </div>
        @empty
            <p class="empty">You are not enrolled in a Hive program yet.</p>
        @endforelse

        <h2>My applications</h2>
        <div class="table-wrap" tabindex="0" role="region" aria-label="Applications">
            <table>
                <thead><tr><th scope="col">Program</th><th scope="col">Cohort</th><th scope="col">Submitted</th><th scope="col">Status</th><th scope="col">Updated</th></tr></thead>
                <tbody>
                @forelse ($applications as $application)
                    <tr>
                        <td>@if ($application->program_title){{ $application->program_title }}@else<span class="meta">Not given</span>@endif</td>
                        <td>@if ($application->cohort_name){{ $application->cohort_name }}@else<span class="meta">Not given</span>@endif</td>
                        <td>@if ($application->submitted_at){{ $application->submitted_at->format('j M Y') }}@else<span class="meta">Not submitted</span>@endif</td>
                        <td><span class="badge badge-{{ in_array($application->status, ['Accepted', 'Selected'], true) ? 'on' : ($application->status === 'Rejected' ? 'off' : 'draft') }}">{{ $application->status }}</span></td>
                        <td>{{ ($application->status_changed_at ?? $application->updated_at)->format('j M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">No applications yet. When you apply to a Hive program, choose “Prefill from my Hive NSP startup profile” so it appears here.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
