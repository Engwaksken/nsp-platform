@extends('layouts.app', ['pageTitle' => 'Members · '.$organization->name])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    @php($roles = config('organizations.roles'))
    @php($canManage = $membership->may('manage_members') && !$organization->isSuspended())
    <div class="container section">
        <h1>Members · {{ $organization->name }}</h1>
        @include('organizations.partials.nav')
        @include('partials.flash')

        @if ($canManage)
            <form method="post" action="{{ route('organizations.members.invite', $organization) }}" class="panel">
                @csrf
                <h2>Invite someone</h2>
                <p class="hint">They get an email link and must sign in with that same email address to join.</p>
                <div class="form-row">
                    @include('partials.field', ['name' => 'email', 'label' => 'Email address', 'type' => 'email', 'required' => true])
                    @include('partials.field', ['name' => 'role', 'label' => 'Role', 'type' => 'select', 'required' => true, 'value' => 'analyst', 'options' => $roles])
                    <div><button type="submit" class="btn btn-primary">Send invitation</button></div>
                </div>
            </form>
        @endif

        <div class="table-wrap" tabindex="0" role="region" aria-label="Members">
            <table>
                <thead><tr><th scope="col">Person</th><th scope="col">Role</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead>
                <tbody>
                @foreach ($members as $member)
                    @php($self = $member->user_id === auth()->id())
                    <tr>
                        <td>{{ $member->user?->name ?? $member->invited_email }}@if ($self) (you)@endif</td>
                        <td>
                            @if ($canManage && !$self && $member->status === 'active')
                                <form method="post" action="{{ route('organizations.members.role', [$organization, $member]) }}" class="actions">
                                    @csrf @method('PUT')
                                    <label class="visually-hidden" for="role-{{ $member->id }}">Role for {{ $member->user?->name }}</label>
                                    <select id="role-{{ $member->id }}" name="role">
                                        @foreach ($roles as $key => $label)<option value="{{ $key }}" @selected($member->role === $key)>{{ $label }}</option>@endforeach
                                    </select>
                                    <button type="submit" class="btn btn-secondary btn-sm">Save<span class="visually-hidden"> role for {{ $member->user?->name }}</span></button>
                                </form>
                            @else
                                {{ $member->roleLabel() }}
                            @endif
                        </td>
                        <td>{{ ucfirst($member->status) }}</td>
                        <td>
                            @if (($canManage && !$self) || ($self && $member->status === 'active'))
                                <form method="post" action="{{ route('organizations.members.remove', [$organization, $member]) }}" data-confirm="{{ $self ? 'Leave '.$organization->name.'?' : 'Remove this person? Their access ends immediately.' }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-secondary btn-sm">{{ $self ? 'Leave' : ($member->status === 'invited' ? 'Cancel invitation' : 'Remove') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
