@extends('layouts.public', ['pageTitle' => $vacancy->title.' · Applications'])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="container section">
        <p><a href="{{ route('workspace.talent', $startup) }}">← All vacancies</a></p>
        <h1>{{ $vacancy->title }}</h1>
        @include('partials.flash')
        <p class="meta">{{ \App\Models\Talent\Vacancy::TYPES[$vacancy->type] }} · <span class="badge">{{ \App\Models\Talent\Vacancy::STATUSES[$vacancy->status] }}</span>
            · <a href="{{ route('workspace.talent.edit', [$startup, $vacancy]) }}">Edit or publish</a>
            · <a href="{{ route('workspace.talent.export', [$startup, $vacancy]) }}">Download applications (CSV)</a></p>

        <nav class="tags" aria-label="Filter by stage">
            <a class="tag" href="{{ route('workspace.talent.vacancy', [$startup, $vacancy]) }}" @if (! $status) aria-current="true" @endif>All ({{ $counts->sum() }})</a>
            @foreach (\App\Models\Talent\JobApplication::STATUSES as $value => $label)
                @if ($counts[$value] ?? 0)<a class="tag" href="{{ route('workspace.talent.vacancy', [$startup, $vacancy, 'status' => $value]) }}" @if ($status === $value) aria-current="true" @endif>{{ $label }} ({{ $counts[$value] }})</a>@endif
            @endforeach
        </nav>

        <div class="table-wrap" tabindex="0" role="region" aria-label="Applications">
            <table>
                <thead><tr><th scope="col">Candidate</th><th scope="col">Headline</th><th scope="col">Stage</th><th scope="col">Applied</th><th scope="col">CV</th></tr></thead>
                <tbody>
                @forelse ($applications as $application)
                    <tr>
                        <td><a href="{{ route('workspace.talent.application', [$startup, $application]) }}">{{ $application->applicant?->name ?? 'Deleted account' }}</a>@if ($application->invited) <span class="badge">Invited</span>@endif</td>
                        <td>{{ $profiles[$application->applicant_id]->headline ?? '—' }}</td>
                        <td>{{ \App\Models\Talent\JobApplication::STATUSES[$application->status] }}</td>
                        <td>{{ $application->created_at->format('j M Y') }}</td>
                        <td>{{ $application->cv_path ? 'Yes' : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">No applications{{ $status ? ' at this stage' : ' yet' }}.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
