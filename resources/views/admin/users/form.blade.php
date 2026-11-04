@extends('layouts.admin')
@section('title', $user->exists ? 'Edit user' : 'Add user')

@section('breadcrumbs')
    <li><a href="{{ route('admin.users.index') }}">Users and roles</a></li>
    <li aria-current="page">{{ $user->exists ? $user->name : 'Add user' }}</li>
@endsection

@section('content')
    <div class="page-head"><h1>{{ $user->exists ? 'Edit: ' . $user->name : 'Add user' }}</h1></div>
    <form method="post" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}" class="panel">
        @csrf
        @if ($user->exists) @method('PUT') @endif
        @include('partials.field', ['name' => 'name', 'label' => 'Name', 'value' => $user->name, 'required' => true, 'autocomplete' => 'off'])
        @include('partials.field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => $user->email, 'required' => true, 'autocomplete' => 'off'])
        @include('partials.field', ['name' => 'password', 'label' => $user->exists ? 'New password (optional)' : 'Password (optional)', 'type' => 'password', 'value' => '', 'autocomplete' => 'new-password',
            'hint' => 'At least 12 characters with letters and numbers. Leave empty for accounts that sign in only with Hive single sign-on.'])
        <fieldset class="form-group">
            <legend class="label">Platform roles</legend>
            @php($current = old('roles', $user->exists ? $user->getRoleNames()->all() : []))
            @foreach ($roles as $role)
                <label class="checkbox"><input type="checkbox" name="roles[]" value="{{ $role }}" @checked(in_array($role, $current, true))> <span>{{ $role }}</span></label>
            @endforeach
            <p class="hint">Only a super admin can grant super_admin or platform_admin. Startup team roles are managed in the startup registry, not here.</p>
            @error('roles')<p class="error">{{ $message }}</p>@enderror
        </fieldset>
        <div class="form-group"><label class="checkbox"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active))> <span>Account is active</span></label></div>
        <div class="actions">
            <button type="submit" class="btn btn-primary">Save user</button>
            <a class="btn btn-secondary" href="{{ route('admin.users.index') }}">Cancel</a>
        </div>
    </form>

    @if ($user->exists)
        <section class="panel" aria-labelledby="mfa-heading">
            <h2 id="mfa-heading">Two-factor sign-in</h2>
            @if ($user->hasMfa())
                <p>On since {{ $user->mfa_enabled_at->format('j M Y') }}. {{ $user->recoveryCodesLeft() }} recovery codes left.</p>
                @unless ($user->is(auth()->user()))
                    <form method="post" action="{{ route('admin.users.reset-mfa', $user) }}" data-confirm="Reset two-factor sign-in for {{ $user->name }}? Only do this after confirming their identity, for example by phone.">
                        @csrf
                        <button type="submit" class="btn btn-danger">Reset two-factor sign-in</button>
                    </form>
                    <p class="hint">Use this when someone has lost their phone and recovery codes. Confirm who they are first.</p>
                @endunless
            @else
                <p>Not set up.@if ($user->requiresMfa()) They will be asked to set it up before they can use the admin area.@endif</p>
            @endif
        </section>
    @endif
@endsection
