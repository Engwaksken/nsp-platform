<?php

namespace App\Models\Organizations;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An institution tenant (university, ESO, investor, partner, corporate). Never the owner of a startup. */
class Organization extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'slug', 'type', 'country', 'website', 'description', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime', 'suspended_at' => 'datetime'];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function relationships(): HasMany
    {
        return $this->hasMany(OrganizationStartupRelationship::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    /** The active membership of a user, or null. */
    public function membershipOf(?User $user): ?OrganizationMembership
    {
        if (! $user) {
            return null;
        }

        return $this->memberships()->where('user_id', $user->id)->where('status', 'active')->first();
    }

    public function typeLabel(): string
    {
        return config('organizations.types.'.$this->type, ucfirst((string) $this->type));
    }

    public function statusLabel(): string
    {
        return config('organizations.statuses.'.$this->status, ucfirst((string) $this->status));
    }
}
