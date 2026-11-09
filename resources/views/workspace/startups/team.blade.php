@extends('layouts.workspace', ['pageTitle' => 'Team · '.$startup->display_name])

@push('head')<meta name="robots" content="noindex">@endpush

@section('workspace')
    @php($roles = \App\Models\Registry\StartupMembership::ROLES)
    @php($isOwner = $membership->role === 'owner')
    <div class="container section">
        <h1>Team · {{ $startup->display_name }}</h1>
        @include('partials.flash')

        @if ($membership->canManageTeam())
            <form method="post" action="{{ route('workspace.startups.team.invite', $startup) }}" class="panel">
                @csrf
                <h2>Invite someone</h2>
                <p class="hint">They get an email link and must sign in with that same email address to join.</p>
                <div class="form-row">
                    @include('partials.field', ['name' => 'email', 'label' => 'Email address', 'type' => 'email', 'required' => true])
                    @include('partials.field', ['name' => 'role', 'label' => 'Role', 'type' => 'select', 'required' => true, 'value' => 'team_member',
                        'options' => $isOwner ? $roles : array_diff_key($roles, ['owner' => 1, 'admin' => 1])])
                    <div><button type="submit" class="btn btn-primary">Send invitation</button></div>
                </div>
            </form>
        @endif

        <div class="table-wrap" tabindex="0" role="region" aria-label="Team members">
            <table>
                <thead><tr><th scope="col">Person</th><th scope="col">Role</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead>
                <tbody>
                @foreach ($members as $member)
                    @php($self = $member->user_id === auth()->id())
                    @php($canTouch = $membership->canManageTeam() && ($isOwner || !in_array($member->role, ['owner', 'admin'], true)))
                    <tr>
                        <td>{{ $member->user?->name ?? $member->invited_email }}@if ($self) (you)@endif
                            @if ($member->status === 'requested' && $member->request_message)<br><span class="meta">“{{ $member->request_message }}”</span>@endif</td>
                        <td>
                            @if ($member->status === 'active' && $canTouch && !$self)
                                <form method="post" action="{{ route('workspace.startups.team.role', [$startup, $member]) }}" class="actions">
                                    @csrf @method('PUT')
                                    <label class="visually-hidden" for="role-{{ $member->uuid }}">Role for {{ $member->user?->name }}</label>
                                    <select id="role-{{ $member->uuid }}" name="role">
                                        @foreach ($isOwner ? $roles : array_diff_key($roles, ['owner' => 1, 'admin' => 1]) as $value => $label)
                                            <option value="{{ $value }}" @selected($member->role === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-secondary btn-sm">Save</button>
                                </form>
                            @else
                                {{ $roles[$member->role] }}
                            @endif
                        </td>
                        <td><span class="badge badge-{{ ['active' => 'on', 'invited' => 'draft', 'requested' => 'new'][$member->status] ?? 'off' }}">{{ ['active' => 'Active', 'invited' => 'Invited', 'requested' => 'Asked to join'][$member->status] ?? $member->status }}</span>
                            @if ($member->status === 'invited')<br><span class="meta">Expires {{ $member->invite_expires_at?->format('j M') }}</span>@endif</td>
                        <td class="actions">
                            @if ($member->status === 'requested' && $membership->canManageTeam())
                                <form method="post" action="{{ route('workspace.startups.team.decide', [$startup, $member]) }}" class="actions">
                                    @csrf
                                    <input type="hidden" name="decision" value="approve">
                                    <label class="visually-hidden" for="approve-role-{{ $member->uuid }}">Role</label>
                                    <select id="approve-role-{{ $member->uuid }}" name="role">
                                        @foreach ($isOwner ? $roles : array_diff_key($roles, ['owner' => 1, 'admin' => 1]) as $value => $label)
                                            <option value="{{ $value }}" @selected($value === 'team_member')>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-primary btn-sm">Approve</button>
                                </form>
                                <form method="post" action="{{ route('workspace.startups.team.decide', [$startup, $member]) }}">
                                    @csrf
                                    <input type="hidden" name="decision" value="decline">
                                    <button type="submit" class="btn btn-secondary btn-sm">Decline</button>
                                </form>
                            @elseif ($self && $member->status === 'active')
                                <form method="post" action="{{ route('workspace.startups.team.remove', [$startup, $member]) }}" data-confirm="Leave {{ $startup->display_name }}? You will lose access to its profile.">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-secondary btn-sm">Leave</button>
                                </form>
                            @elseif ($canTouch)
                                <form method="post" action="{{ route('workspace.startups.team.remove', [$startup, $member]) }}" data-confirm="{{ $member->status === 'invited' ? 'Cancel this invitation?' : 'Remove this person from the team?' }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">{{ $member->status === 'invited' ? 'Cancel invite' : 'Remove' }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <p class="hint">Roles: owners and admins manage the team (only owners manage other owners and admins); owners, founders and admins edit the profile; team members and viewers can see it. A startup always keeps at least one owner.</p>
    </div>
@endsection
