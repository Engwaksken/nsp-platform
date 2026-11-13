@extends('layouts.workspace', ['pageTitle' => 'Team · '.$startup->display_name])

@push('head')<meta name="robots" content="noindex">@endpush

@section('workspace')
    @php($roles = \App\Models\Registry\StartupMembership::ROLES)
    @php($isOwner = $membership->role === 'owner')
    @php($initials = fn (?string $name) => collect(preg_split('/\s+/', trim((string) $name)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('') ?: '?')
    <div class="container section">
        <div class="page-head">
            <div>
                <p class="profile-hero-eyebrow">{{ $startup->display_name }}</p>
                <h1>Team</h1>
                <p class="lead-muted">Who works on {{ $startup->display_name }}, their role, and who is waiting to join.</p>
            </div>
            @if ($membership->canManageTeam())
                <a class="btn btn-primary" href="#invite"><i class="fas fa-user-plus" aria-hidden="true"></i> Invite someone</a>
            @endif
        </div>
        @include('partials.flash')

        @include('partials.stats', ['label' => 'Team at a glance', 'stats' => [
            ['icon' => 'fa-users', 'tone' => 'purple', 'label' => 'Active members', 'value' => $members->where('status', 'active')->count()],
            ['icon' => 'fa-envelope-open-text', 'tone' => 'blue', 'label' => 'Invitations pending', 'value' => $members->where('status', 'invited')->count()],
            ['icon' => 'fa-user-clock', 'tone' => 'amber', 'label' => 'Asked to join', 'value' => $members->where('status', 'requested')->count()],
            ['icon' => 'fa-crown', 'tone' => 'orange', 'label' => 'Owners', 'value' => $members->where('status', 'active')->where('role', 'owner')->count()],
        ]])

        <div data-tabs="Team sections" data-tabs-key="team">
        <section data-tab="Members" data-tab-icon="fa-users" data-tab-count="{{ $members->count() }}" aria-label="Members">
        <div class="table-wrap" tabindex="0" role="region" aria-label="Team members">
            <table>
                <thead><tr><th scope="col">Person</th><th scope="col">Role</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead>
                <tbody>
                @foreach ($members as $member)
                    @php($self = $member->user_id === auth()->id())
                    @php($canTouch = $membership->canManageTeam() && ($isOwner || !in_array($member->role, ['owner', 'admin'], true)))
                    <tr>
                        <td>
                            <div class="person-cell">
                                <span class="person-avatar person-avatar-sm" aria-hidden="true">{{ $member->user ? $initials($member->user->name) : '@' }}</span>
                                <div>
                                    <strong>{{ $member->user?->name ?? $member->invited_email }}</strong>@if ($self) <span class="person-you">You</span>@endif
                                    @if ($member->user && $member->status === 'active' && $member->accepted_at)<span class="meta">Joined {{ $member->accepted_at->format('j M Y') }}</span>
                                    @elseif ($member->status === 'invited')<span class="meta">Invitation sent</span>@endif
                                    @if ($member->status === 'requested' && $member->request_message)<span class="meta">“{{ $member->request_message }}”</span>@endif
                                </div>
                            </div>
                        </td>
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
                                    <button type="submit" class="btn btn-icon btn-icon-primary" data-tip="Save role"><i class="fas fa-floppy-disk" aria-hidden="true"></i><span class="visually-hidden">Save role for {{ $member->user?->name }}</span></button>
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
                                    <button type="submit" class="btn btn-icon btn-icon-success" data-tip="Approve"><i class="fas fa-check" aria-hidden="true"></i><span class="visually-hidden">Approve {{ $member->user?->name }}</span></button>
                                </form>
                                <form method="post" action="{{ route('workspace.startups.team.decide', [$startup, $member]) }}">
                                    @csrf
                                    <input type="hidden" name="decision" value="decline">
                                    <button type="submit" class="btn btn-icon btn-icon-danger" data-tip="Decline"><i class="fas fa-xmark" aria-hidden="true"></i><span class="visually-hidden">Decline {{ $member->user?->name }}</span></button>
                                </form>
                            @elseif ($self && $member->status === 'active')
                                <form method="post" action="{{ route('workspace.startups.team.remove', [$startup, $member]) }}" data-confirm="Leave {{ $startup->display_name }}? You will lose access to its profile.">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-icon btn-icon-danger" data-tip="Leave"><i class="fas fa-right-from-bracket" aria-hidden="true"></i><span class="visually-hidden">Leave {{ $startup->display_name }}</span></button>
                                </form>
                            @elseif ($canTouch)
                                <form method="post" action="{{ route('workspace.startups.team.remove', [$startup, $member]) }}" data-confirm="{{ $member->status === 'invited' ? 'Cancel this invitation?' : 'Remove this person from the team?' }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-icon btn-icon-danger" data-tip="{{ $member->status === 'invited' ? 'Cancel invite' : 'Remove' }}"><i class="fas {{ $member->status === 'invited' ? 'fa-envelope-circle-check' : 'fa-user-minus' }}" aria-hidden="true"></i><span class="visually-hidden">{{ $member->status === 'invited' ? 'Cancel invite' : 'Remove' }} {{ $member->user?->name ?? $member->invited_email }}</span></button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <p class="hint">Roles: owners and admins manage the team (only owners manage other owners and admins); owners, founders and admins edit the profile; team members and viewers can see it. A startup always keeps at least one owner.</p>
        </section>

        @if ($membership->canManageTeam())
            <section id="invite" data-tab="Invite someone" data-tab-icon="fa-user-plus" aria-label="Invite someone">
            <form method="post" action="{{ route('workspace.startups.team.invite', $startup) }}" class="panel">
                @csrf
                <h2 id="invite-heading">Invite someone</h2>
                <p class="hint">They get an email link and must sign in with that same email address to join.</p>
                <div class="form-row">
                    @include('partials.field', ['name' => 'email', 'label' => 'Email address', 'type' => 'email', 'required' => true])
                    @include('partials.field', ['name' => 'role', 'label' => 'Role', 'type' => 'select', 'required' => true, 'value' => 'team_member',
                        'options' => $isOwner ? $roles : array_diff_key($roles, ['owner' => 1, 'admin' => 1])])
                    <div><button type="submit" class="btn btn-primary">Send invitation</button></div>
                </div>
            </form>
            </section>
        @endif
        </div>
    </div>
@endsection
