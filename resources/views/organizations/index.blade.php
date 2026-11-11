@extends('layouts.app', ['pageTitle' => 'My organizations'])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="container section narrow">
        <h1>My organizations</h1>
        @include('partials.flash')
        <p>Organizations such as universities, support organizations, investors and partners see a startup only when that startup’s owners or founders approve, and only the items they choose.</p>

        @if ($memberships->isEmpty())
            <p class="empty">You are not a member of any organization yet. An organization admin or the Hive team can invite you by email.</p>
        @else
            <div class="table-wrap" tabindex="0" role="region" aria-label="My organizations">
                <table>
                    <thead><tr><th scope="col">Organization</th><th scope="col">Type</th><th scope="col">Your role</th><th scope="col">Status</th><th scope="col">Working as</th></tr></thead>
                    <tbody>
                    @foreach ($memberships as $m)
                        <tr>
                            <td><a href="{{ route('organizations.show', $m->organization) }}">{{ $m->organization->name }}</a></td>
                            <td>{{ $m->organization->typeLabel() }}</td>
                            <td>{{ $m->roleLabel() }}</td>
                            <td><span class="badge badge-{{ $m->organization->isActive() ? 'on' : ($m->organization->isSuspended() ? 'off' : 'draft') }}">{{ $m->organization->statusLabel() }}</span></td>
                            <td>
                                @if ($current && $current->organization_id === $m->organization_id)
                                    <span class="badge badge-on">Current</span>
                                @elseif (!$m->organization->isSuspended())
                                    <form method="post" action="{{ route('organizations.switch', $m->organization) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-secondary btn-sm">Switch<span class="visually-hidden"> to {{ $m->organization->name }}</span></button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
