<?php

namespace App\Models\Organizations;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationMembership extends Model
{
    protected $fillable = ['organization_id', 'user_id', 'role', 'status', 'invited_email', 'invite_token_hash',
        'invite_expires_at', 'invited_by', 'accepted_at', 'revoked_at', 'active_key'];

    protected $hidden = ['invite_token_hash', 'active_key'];

    protected function casts(): array
    {
        return ['invite_expires_at' => 'datetime', 'accepted_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function roleLabel(): string
    {
        return config('organizations.roles.'.$this->role, $this->role);
    }

    /** @param  string  $right  manage_members | request_access | view_portfolio */
    public function may(string $right): bool
    {
        return $this->status === 'active' && in_array($this->role, (array) config('organizations.role_rights.'.$right, []), true);
    }

    public static function activeKey(int $organizationId, int $userId): string
    {
        return 'u:'.$organizationId.':'.$userId;
    }

    public static function inviteKey(int $organizationId, string $email): string
    {
        return 'e:'.$organizationId.':'.hash('sha256', strtolower($email));
    }
}
