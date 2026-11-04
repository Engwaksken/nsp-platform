<?php

namespace App\Models\Talent;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TalentProfile extends Model
{
    public const VISIBILITY = [
        'hidden' => 'Only me. Startups see my details when I apply.',
        'startups' => 'Startups on NSP can find my headline, skills, location and work preferences, and invite me to apply.',
    ];

    protected $fillable = ['headline', 'summary', 'skills', 'work_types', 'location', 'country', 'open_to_work', 'visibility'];

    /** The CV path never leaves the server. */
    protected $hidden = ['cv_path'];

    protected function casts(): array
    {
        return ['skills' => 'array', 'work_types' => 'array', 'open_to_work' => 'boolean', 'consented_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::deleted(fn (TalentProfile $profile) => $profile->cv_path && Storage::disk('local')->delete($profile->cv_path));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Profiles a startup may search: opted in, open to work, active account. */
    public function scopeSearchable(Builder $query): Builder
    {
        return $query->where('visibility', 'startups')->whereNotNull('consented_at')->where('open_to_work', true)
            ->whereHas('user', fn ($q) => $q->where('is_active', true));
    }

    /** Normalise free-text skills: trimmed, unique, at most 15, 40 characters each. */
    public static function normaliseSkills(?string $text): array
    {
        return collect(preg_split('/[,\n;]+/', (string) $text))
            ->map(fn ($s) => mb_substr(trim(preg_replace('/\s+/', ' ', $s)), 0, 40))->filter()
            ->unique(fn ($s) => mb_strtolower($s))->take(15)->values()->all();
    }
}
