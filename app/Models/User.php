<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, MustVerifyEmail, Notifiable;

    /** Roles that give access to the admin area. */
    public const ADMIN_ROLES = ['super_admin', 'platform_admin', 'ecosystem_manager', 'content_editor', 'support', 'auditor'];

    /** Roles only a super admin may grant or remove. */
    public const PRIVILEGED_ROLES = ['super_admin', 'platform_admin'];

    protected $fillable = ['name', 'email', 'password', 'is_active'];

    protected $hidden = ['password', 'remember_token', 'mfa_secret', 'mfa_recovery_codes'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
            'mfa_secret' => 'encrypted',
            'mfa_recovery_codes' => 'encrypted:array',
            'mfa_enabled_at' => 'datetime',
        ];
    }

    /** Up to two initials for avatars, e.g. "Olive Founder" -> "OF". */
    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim((string) $this->name)))->filter()->take(2)
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('') ?: '?';
    }

    public function canUseAdmin(): bool
    {
        return $this->is_active && $this->hasAnyRole(self::ADMIN_ROLES);
    }

    /** Everyone who can reach the admin area must use a second factor. */
    public function requiresMfa(): bool
    {
        return (bool) config('nsp.mfa.enforce') && $this->canUseAdmin();
    }

    public function hasMfa(): bool
    {
        return $this->mfa_enabled_at !== null && filled($this->mfa_secret);
    }

    /**
     * Turn on two-factor sign-in and return new one-time recovery codes (shown once, stored hashed).
     *
     * @return list<string>
     */
    public function enableMfa(string $secret, int $step): array
    {
        $codes = self::newRecoveryCodes();
        $this->forceFill([
            'mfa_secret' => $secret,
            'mfa_recovery_codes' => array_map(fn ($code) => hash('sha256', $code), $codes),
            'mfa_enabled_at' => now(),
            'mfa_last_step' => $step,
        ])->save();

        return $codes;
    }

    public function disableMfa(): void
    {
        $this->forceFill(['mfa_secret' => null, 'mfa_recovery_codes' => null, 'mfa_enabled_at' => null, 'mfa_last_step' => null])->save();
    }

    /** @return list<string> */
    public function regenerateRecoveryCodes(): array
    {
        $codes = self::newRecoveryCodes();
        $this->forceFill(['mfa_recovery_codes' => array_map(fn ($code) => hash('sha256', $code), $codes)])->save();

        return $codes;
    }

    /** Uses up a recovery code if it matches. */
    public function useRecoveryCode(string $code): bool
    {
        $hash = hash('sha256', strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? ''));
        $remaining = $this->mfa_recovery_codes ?? [];
        foreach ($remaining as $index => $stored) {
            if (hash_equals($stored, $hash)) {
                unset($remaining[$index]);
                $this->forceFill(['mfa_recovery_codes' => array_values($remaining)])->save();

                return true;
            }
        }

        return false;
    }

    public function recoveryCodesLeft(): int
    {
        return count($this->mfa_recovery_codes ?? []);
    }

    /** @return list<string> */
    private static function newRecoveryCodes(): array
    {
        return array_map(fn () => strtoupper(Str::random(5).Str::random(5)), range(1, 8));
    }
}
