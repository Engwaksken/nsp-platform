@extends('layouts.admin')
@section('title', 'Organizations')

@section('content')
    @php
        $statuses = config('organizations.statuses');
        $types = config('organizations.types');
        $tone = ['active' => 'badge-on', 'pending' => 'badge-new', 'suspended' => 'badge-off'];
    @endphp
    <div class="page-head">
        <h1>Organizations</h1>
        <a class="btn btn-primary" href="#new-organization"><i class="fas fa-plus" aria-hidden="true"></i> New organization</a>
    </div>
    <p class="hint">Universities, ESOs, investors, development partners and corporates. Verify an organization before it can follow startups; startups decide what each one may see.</p>
    @include('partials.flash')

    <form method="get" class="filters" role="search" aria-label="Filter organizations">
        <div class="form-group"><label for="q">Name</label><input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Organization name" maxlength="100"></div>
        <div class="form-group"><label for="status">Status</label>
            <select id="status" name="status">
                <option value="">Any</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-secondary" type="submit">Filter</button>
    </form>

    <div class="table-wrap" tabindex="0" role="region" aria-label="Organizations">
        <table>
            <thead><tr><th scope="col">Organization</th><th scope="col">Type</th><th scope="col">Country</th><th scope="col">Members</th><th scope="col">Status</th><th scope="col">Added</th></tr></thead>
            <tbody>
            @forelse ($organizations as $organization)
                <tr>
                    <td><a href="{{ route('admin.organizations.show', $organization) }}">{{ $organization->name }}</a>
                        @if ($organization->website)<br><span class="meta">{{ preg_replace('#^https?://(www\.)?#', '', rtrim($organization->website, '/')) }}</span>@endif</td>
                    <td>{{ $organization->typeLabel() }}</td>
                    <td>@if ($organization->country){{ \App\Models\Registry\Startup::COUNTRIES[$organization->country] ?? $organization->country }}@else<span class="meta">Not given</span>@endif</td>
                    <td>{{ $organization->members_count }}</td>
                    <td><span class="badge {{ $tone[$organization->status] ?? 'badge-draft' }}">{{ $organization->statusLabel() }}</span></td>
                    <td>{{ $organization->created_at?->format('j M Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No organizations found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $organizations->links() }}

    <section class="panel" id="new-organization" aria-labelledby="new-heading">
        <h2 id="new-heading">New organization</h2>
        <p class="hint">It starts as pending. Verify it on its page, then invite its first admin.</p>
        <form method="post" action="{{ route('admin.organizations.store') }}">
            @csrf
            <div class="form-row">
                @include('partials.field', ['name' => 'name', 'label' => 'Name', 'required' => true, 'maxlength' => 200])
                @include('partials.field', ['name' => 'type', 'label' => 'Type', 'type' => 'select', 'options' => $types, 'placeholder' => 'Choose…', 'required' => true])
                @include('partials.field', ['name' => 'country', 'label' => 'Country', 'type' => 'select', 'options' => \App\Models\Registry\Startup::COUNTRIES, 'placeholder' => 'Not given'])
            </div>
            @include('partials.field', ['name' => 'website', 'label' => 'Website', 'type' => 'url', 'maxlength' => 255])
            @include('partials.field', ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'rows' => 3])
            <button type="submit" class="btn btn-primary">Create organization</button>
        </form>
    </section>
@endsection
