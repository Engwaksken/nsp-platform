<?php

namespace App\Models\Experts;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpertProfile extends Model
{
    public const KINDS = ['mentor' => 'Mentor', 'consultant' => 'Consultant', 'both' => 'Mentor and consultant'];

    public const LANGUAGES = ['en' => 'English', 'lg' => 'Luganda', 'sw' => 'Swahili', 'fr' => 'French', 'rw' => 'Kinyarwanda', 'other' => 'Other'];

    protected $fillable = ['headline', 'bio', 'kind', 'expertise', 'sectors', 'languages', 'website', 'availability',
        'offers_free', 'rate_note', 'max_active', 'is_listed', 'location', 'is_remote', 'time_zone'];

    protected function casts(): array
    {
        return [
            'expertise' => 'array', 'sectors' => 'array', 'languages' => 'array',
            'offers_free' => 'boolean', 'is_listed' => 'boolean', 'is_remote' => 'boolean', 'verified_at' => 'datetime', 'max_active' => 'integer',
        ];
    }

    public static function areas(): array
    {
        return config('nsp.experts.areas', []);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ExpertCategory::class, 'expert_category_profile');
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(ExpertVerification::class)->latest();
    }

    public function services(): HasMany
    {
        return $this->hasMany(ServicePackage::class, 'expert_user_id', 'user_id');
    }

    /** The expert's IANA time zone (falls back to the platform default). */
    public function timezone(): string
    {
        $zone = (string) ($this->time_zone ?: config('experts-marketplace.default_timezone', 'Africa/Kampala'));

        return in_array($zone, timezone_identifiers_list(), true) ? $zone : 'UTC';
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('status', 'verified')->whereHas('user', fn ($q) => $q->where('is_active', true));
    }

    public function isVerified(): bool
    {
        return $this->status === 'verified' && $this->user?->is_active;
    }

    public function offersMentoring(): bool
    {
        return in_array($this->kind, ['mentor', 'both'], true);
    }

    public function areaLabels(): array
    {
        return array_values(array_intersect_key(self::areas(), array_flip($this->expertise ?? [])));
    }
}
