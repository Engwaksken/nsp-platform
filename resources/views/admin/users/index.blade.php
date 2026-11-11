@extends('layouts.admin')
@section('title', 'Users and roles')

@section('content')
    <div class="page-head">
        <h1>Users and roles</h1>
        <a class="btn btn-primary" href="{{ route('admin.users.create') }}" data-modal>Add user</a>
    </div>
    @include('partials.stats', ['label' => 'Summary', 'stats' => \App\Support\AdminStats::users()])
    <form method="get" class="filters" role="search">
        <div class="form-group"><label for="q">Name or email</label><input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Search by name or email…"></div>
        <div class="form-group">
            <label for="role">Role</label>
            <select id="role" name="role"><option value="">Any</option>
                @foreach ($roles as $role)<option value="{{ $role }}" @selected(request('role') === $role)>{{ $role }}</option>@endforeach
            </select>
        </div>
        <button class="btn btn-secondary" type="submit">Filter</button>
    </form>
    <div class="table-wrap" tabindex="0" role="region" aria-label="Scrollable table">
        <table>
            <thead><tr><th scope="col">Name</th><th scope="col">Email</th><th scope="col">Roles</th><th scope="col">Sign-in</th><th scope="col">Status</th><th scope="col">Last sign-in</th></tr></thead>
            <tbody>
            @forelse ($users as $user)
                <tr>
                    <td><a href="{{ route('admin.users.edit', $user) }}" data-modal>{{ $user->name }}</a></td>
                    <td>{{ $user->email }}</td>
                    <td>@if ($user->roles->isNotEmpty()){{ $user->roles->pluck('name')->join(', ') }}@else<span class="meta">None</span>@endif</td>
                    <td>@if ($user->oidc_subject || $user->password){{ collect([$user->oidc_subject ? 'Hive SSO' : null, $user->password ? 'Password' : null])->filter()->join(', ') }}@else<span class="meta">None</span>@endif</td>
                    <td><span class="badge badge-{{ $user->is_active ? 'on' : 'off' }}">{{ $user->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td>{{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No users found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $users->links() }}
@endsection
