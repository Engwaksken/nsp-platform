@extends('layouts.admin')
@section('title', 'Registrations')

@section('breadcrumbs')
    <li><a href="{{ route('admin.opportunities.index') }}">Opportunities</a></li>
    <li><a href="{{ route('admin.opportunities.edit', $opportunity) }}">{{ $opportunity->title }}</a></li>
    <li aria-current="page">Registrations</li>
@endsection

@section('content')
    <div class="page-head">
        <h1>Registrations · {{ $opportunity->title }}</h1>
        <a class="btn btn-secondary" href="{{ route('admin.opportunities.export', $opportunity) }}">Download CSV</a>
    </div>
    <p class="hint">{{ $interests->whereIn('status', ['registered', 'confirmed'])->count() }} active @if ($opportunity->capacity) of {{ $opportunity->capacity }} places @endif. Downloads are recorded in the audit log.</p>
    <div class="table-wrap" tabindex="0" role="region" aria-label="Registrations">
        <table>
            <thead><tr><th scope="col">Person</th><th scope="col">Startup</th><th scope="col">Message</th><th scope="col">Registered</th><th scope="col">Status</th></tr></thead>
            <tbody>
            @forelse ($interests as $interest)
                <tr>
                    <td>{{ $interest->user?->name }}<br><span class="meta">{{ $interest->user?->email }}</span></td>
                    <td>@if ($interest->startup){{ $interest->startup->display_name }}@else<span class="meta">None</span>@endif</td>
                    <td>{{ $interest->message }}</td>
                    <td>{{ $interest->created_at->format('j M Y') }}</td>
                    <td>
                        <form method="post" action="{{ route('admin.opportunities.interests.update', [$opportunity, $interest]) }}" class="actions">
                            @csrf @method('PUT')
                            <label class="visually-hidden" for="st-{{ $interest->id }}">Status for {{ $interest->user?->name }}</label>
                            <select id="st-{{ $interest->id }}" name="status">
                                @foreach (\App\Models\Ecosystem\OpportunityInterest::STATUSES as $value => $label)<option value="{{ $value }}" @selected($interest->status === $value)>{{ $label }}</option>@endforeach
                            </select>
                            <button type="submit" class="btn btn-secondary btn-sm">Save</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">No registrations yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
