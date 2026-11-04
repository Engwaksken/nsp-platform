<?php

namespace App\Models\Experts;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpertProfile extends Model
{
    public const KINDS = ['mentor' => 'Mentor', 'consultant' => 'Consultant', 'both' => 'Mentor and consultant'];

    public const LANGUAGES = ['en' => 'English', 'lg' => 'Luganda', 'sw' => 'Swahili', 'fr' => 'French', 'rw' => 'Kinyarwanda', 'other' => 'Other'];

    protected $fillable = ['headline', 'bio', 'kind', 'expertise', 'sectors', 'languages', 'website', 'availability',
        'offers_free', 'rate_note', 'max_active', 'is_listed'];

    protected function casts(): array
    {
        return [
            'expertise' => 'array', 'sectors' => 'array', 'languages' => 'array',
            'offers_free' => 'boolean', 'is_listed' => 'boolean', 'verified_at' => 'datetime', 'max_active' => 'integer',
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
