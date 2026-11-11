@extends('layouts.admin')
@section('title', $organization->name)

@section('content')
    @php
        $tone = ['active' => 'badge-on', 'pending' => 'badge-new', 'suspended' => 'badge-off'];
        $roles = config('organizations.roles');
    @endphp
    <div class="page-head">
        <div>
            <p class="meta"><a href="{{ route('admin.organizations.index') }}"><i class="fas fa-arrow-left" aria-hidden="true"></i> Organizations</a></p>
            <h1>{{ $organization->name }} <span class="badge {{ $tone[$organization->status] ?? 'badge-draft' }}">{{ $organization->statusLabel() }}</span></h1>
            <p class="meta">{{ $organization->typeLabel() }}@if ($organization->verified_at) · verified {{ $organization->verified_at->format('j M Y') }}@endif</p>
        </div>
        <div class="actions">
            @foreach ([
                'verify' => [$organization->status === 'pending', 'Verify', 'btn-primary', null],
                'suspend' => [$organization->status === 'active', 'Suspend', 'btn-danger', 'Suspend '.$organization->name.'? Its members lose access to shared startup data until it is reactivated.'],
                'reactivate' => [$organization->status === 'suspended', 'Reactivate', 'btn-primary', null],
            ] as $action => [$show, $label, $class, $confirm])
                @if ($show)
                    <form method="post" action="{{ route('admin.organizations.update', $organization) }}" @if ($confirm) data-confirm="{{ $confirm }}" @endif>
                        @csrf @method('PUT')
                        <input type="hidden" name="action" value="{{ $action }}">
                        <button type="submit" class="btn {{ $class }}">{{ $label }}</button>
                    </form>
                @endif
            @endforeach
        </div>
    </div>
    @include('partials.flash')
    @error('organization')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror

    <div data-tabs="Organization sections" data-tabs-key="admin-organization">
        <section class="panel" id="details" aria-labelledby="details-heading" data-tab="Details" data-tab-icon="fa-building">
            <h2 id="details-heading">Details</h2>
            <form method="post" action="{{ route('admin.organizations.update', $organization) }}">
                @csrf @method('PUT')
                <div class="form-row">
                    @include('partials.field', ['name' => 'name', 'label' => 'Name', 'value' => $organization->name, 'required' => true, 'maxlength' => 200])
                    @include('partials.field', ['name' => 'type', 'label' => 'Type', 'type' => 'select', 'options' => config('organizations.types'), 'value' => $organization->type, 'required' => true])
                    @include('partials.field', ['name' => 'country', 'label' => 'Country', 'type' => 'select', 'options' => \App\Models\Registry\Startup::COUNTRIES, 'placeholder' => 'Not given', 'value' => $organization->country])
                </div>
                @include('partials.field', ['name' => 'website', 'label' => 'Website', 'type' => 'url', 'value' => $organization->website, 'maxlength' => 255])
                @include('partials.field', ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'rows' => 3, 'value' => $organization->description])
                <button type="submit" class="btn btn-primary">Save details</button>
            </form>
        </section>

        <section class="panel" id="members" aria-labelledby="members-heading" data-tab="Members" data-tab-icon="fa-users" data-tab-count="{{ $members->count() }}">
            <h2 id="members-heading">Members</h2>
            @error('email')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
            <div class="table-wrap" tabindex="0" role="region" aria-label="Members">
                <table>
                    <thead><tr><th scope="col">Person</th><th scope="col">Role</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
                    <tbody>
                    @forelse ($members as $member)
                        <tr>
                            <td>{{ $member->user?->name ?? $member->invited_email }}@if ($member->user)<br><span class="meta">{{ $member->user->email }}</span>@endif</td>
                            <td>{{ $member->roleLabel() }}</td>
                            <td><span class="badge {{ $member->status === 'active' ? 'badge-on' : 'badge-draft' }}">{{ $member->status === 'active' ? 'Active' : 'Invited' }}</span>
                                @if ($member->status === 'invited' && $member->invite_expires_at)<br><span class="meta">Expires {{ $member->invite_expires_at->format('j M') }}</span>@endif</td>
                            <td>
                                <form method="post" action="{{ route('admin.organizations.members', $organization) }}" data-confirm="{{ $member->status === 'invited' ? 'Cancel this invitation?' : 'Remove this member from '.$organization->name.'?' }}">
                                    @csrf
                                    <input type="hidden" name="remove" value="{{ $member->id }}">
                                    <button type="submit" class="btn btn-secondary btn-sm">{{ $member->status === 'invited' ? 'Cancel invite' : 'Remove' }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty">No members yet. Invite the organization's first admin below.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <form method="post" action="{{ route('admin.organizations.members', $organization) }}" class="panel">
                @csrf
                <h3>Invite someone</h3>
                <p class="hint">They get an email link and must sign in with that address to join.@if ($organization->status !== 'active') Verify the organization first if the invitation is refused.@endif</p>
                <div class="form-row">
                    @include('partials.field', ['name' => 'email', 'label' => 'Email address', 'type' => 'email', 'required' => true])
                    @include('partials.field', ['name' => 'role', 'label' => 'Role', 'type' => 'select', 'options' => $roles, 'value' => 'org_admin', 'required' => true])
                    <div><button type="submit" class="btn btn-primary">Send invitation</button></div>
                </div>
            </form>
        </section>

        <section class="panel" id="startups" aria-labelledby="startups-heading" data-tab="Startup access" data-tab-icon="fa-handshake" data-tab-count="{{ $relationships->count() }}">
            <h2 id="startups-heading">Startup access</h2>
            <p class="hint">Requests and approvals between this organization and startups. Startups approve, narrow or revoke access themselves.</p>
            <div class="table-wrap" tabindex="0" role="region" aria-label="Startup access">
                <table>
                    <thead><tr><th scope="col">Startup</th><th scope="col">Purpose</th><th scope="col">Shared items</th><th scope="col">Status</th><th scope="col">Date</th></tr></thead>
                    <tbody>
                    @forelse ($relationships as $relationship)
                        <tr>
                            <td>{{ $relationship->startup?->display_name ?? 'Unknown' }}</td>
                            <td>{{ $relationship->purposeLabel() }}</td>
                            <td>@if ($relationship->scopeLabels()){{ implode(', ', $relationship->scopeLabels()) }}@else<span class="meta">None</span>@endif</td>
                            <td><span class="badge {{ $relationship->isEffective() ? 'badge-on' : ($relationship->status === 'requested' ? 'badge-new' : 'badge-off') }}">{{ ucfirst($relationship->displayStatus()) }}</span></td>
                            <td>{{ $relationship->created_at?->format('j M Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty">No startup has shared with this organization yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
