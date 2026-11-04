<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /** Roles that give access to the admin area. */
    public const ADMIN_ROLES = ['super_admin', 'platform_admin', 'ecosystem_manager', 'content_editor', 'support', 'auditor'];

    /** Roles only a super admin may grant or remove. */
    public const PRIVILEGED_ROLES = ['super_admin', 'platform_admin'];

    protected $fillable = ['name', 'email', 'password', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function canUseAdmin(): bool
    {
        return $this->is_active && $this->hasAnyRole(self::ADMIN_ROLES);
    }
}
