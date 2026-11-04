<?php

namespace App\Models\Registry;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StartupMembership extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';

    public const ROLES = [
        'owner' => 'Owner',
        'founder' => 'Founder',
        'admin' => 'Admin',
        'team_member' => 'Team member',
        'viewer' => 'Viewer',
    ];

    /** Roles that may edit the startup profile. */
    public const EDITOR_ROLES = ['owner', 'founder', 'admin'];

    /** Roles that may invite and manage the team. */
    public const TEAM_MANAGER_ROLES = ['owner', 'admin'];

    /** Roles that may grant or revoke data-sharing consent. */
    public const CONSENT_ROLES = ['owner', 'founder'];

    protected $fillable = ['startup_uuid', 'user_id', 'user_subject', 'role', 'status', 'invited_email', 'invite_token_hash',
        'invite_expires_at', 'request_message', 'invited_by', 'accepted_at', 'revoked_at'];

    protected $hidden = ['invite_token_hash'];

    protected function casts(): array
    {
        return ['invite_expires_at' => 'datetime', 'accepted_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function startup(): BelongsTo
    {
        return $this->belongsTo(Startup::class, 'startup_uuid');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function canEdit(): bool
    {
        return in_array($this->role, self::EDITOR_ROLES, true);
    }

    public function canManageTeam(): bool
    {
        return in_array($this->role, self::TEAM_MANAGER_ROLES, true);
    }

    public function canManageConsent(): bool
    {
        return in_array($this->role, self::CONSENT_ROLES, true);
    }
}
