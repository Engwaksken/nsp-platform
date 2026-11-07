<?php

namespace App\Models\Capital;

use App\Models\Registry\Startup;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** What one founder team shares with one investor (scopes), until a date or until revoked. */
class ShareGrant extends Model
{
    protected $table = 'capital_share_grants';

    protected $fillable = ['startup_uuid', 'investor_user_id', 'scopes', 'granted_by', 'expires_at', 'revoked_by', 'revoked_at'];

    protected function casts(): array
    {
        return ['scopes' => 'array', 'expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function allows(string $scope): bool
    {
        return $this->isActive() && in_array($scope, $this->scopes ?? [], true);
    }

    public function investor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'investor_user_id');
    }

    public function startup(): BelongsTo
    {
        return $this->belongsTo(Startup::class, 'startup_uuid');
    }
}
