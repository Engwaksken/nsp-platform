<?php

namespace App\Models\Organizations;

use App\Models\Registry\Startup;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A scoped, per-purpose access grant between one organization and one startup.
 * It is only effective while status is "active" and expires_at is empty or in the future.
 */
class OrganizationStartupRelationship extends Model
{
    use HasUuids;

    protected $fillable = ['organization_id', 'startup_uuid', 'purpose', 'scopes', 'status', 'initiated_by', 'requested_by',
        'decided_by', 'decided_at', 'expires_at', 'revoked_at', 'revoked_by', 'note', 'open_key'];

    protected $hidden = ['open_key'];

    protected function casts(): array
    {
        return ['scopes' => 'array', 'decided_at' => 'datetime', 'expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function startup(): BelongsTo
    {
        return $this->belongsTo(Startup::class, 'startup_uuid');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** Active and not past its expiry date, evaluated at query time. */
    public function scopeEffective(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function isEffective(): bool
    {
        return $this->status === 'active' && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /** Status to show people: an active grant past its date reads as expired. */
    public function displayStatus(): string
    {
        return $this->status === 'active' && ! $this->isEffective() ? 'expired' : $this->status;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['requested', 'active'], true);
    }

    public function scopeLabels(): array
    {
        return array_map(fn ($s) => config('organizations.scopes.'.$s.'.label', $s), (array) $this->scopes);
    }

    public function purposeLabel(): string
    {
        return config('organizations.purposes.'.$this->purpose, $this->purpose);
    }

    public static function openKey(int $organizationId, string $startupUuid): string
    {
        return $organizationId.':'.$startupUuid;
    }
}
