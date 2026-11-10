@extends('layouts.admin')
@section('title', 'Startups')

@section('content')
    <div class="page-head">
        <h1>Startups</h1>
        @can('review-duplicates')<a class="btn btn-secondary" href="{{ route('admin.duplicates.index') }}">Review possible duplicates</a>@endcan
    </div>
    @include('partials.stats', ['label' => 'Summary', 'stats' => \App\Support\AdminStats::startups()])
    <form method="get" class="filters" role="search">
        <div class="form-group"><label for="q">Name or registration number</label><input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Startup name or registration number"></div>
        <div class="form-group"><label for="status">Status</label>
            <select id="status" name="status">
                @foreach (['active' => 'Active', 'archived' => 'Archived', 'merged' => 'Merged'] as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-secondary" type="submit">Filter</button>
    </form>
    <div class="table-wrap" tabindex="0" role="region" aria-label="Startups">
        <table>
            <thead><tr><th scope="col">Startup</th><th scope="col">Registration</th><th scope="col">Sector</th><th scope="col">Team</th><th scope="col">Profile</th><th scope="col">Registered</th></tr></thead>
            <tbody>
            @forelse ($startups as $startup)
                <tr>
                    <td><a href="{{ route('admin.startups.show', $startup) }}">{{ $startup->display_name }}</a><br><span class="meta">{{ $startup->legal_name }}</span></td>
                    <td>{{ $startup->registration_number ?: '—' }}</td>
                    <td>{{ $startup->profile?->sector ?: '—' }}</td>
                    <td>{{ $startup->active_memberships_count }}</td>
                    <td>{{ $startup->profile?->profile_completion ?? 0 }}%</td>
                    <td>{{ $startup->created_at->format('j M Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No startups found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $startups->links() }}
@endsection
