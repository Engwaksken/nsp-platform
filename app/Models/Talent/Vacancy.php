<?php

namespace App\Models\Talent;

use App\Models\Registry\Startup;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Vacancy extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';

    public const TYPES = ['full_time' => 'Full-time job', 'part_time' => 'Part-time job', 'internship' => 'Internship',
        'gig' => 'Short-term gig', 'volunteer' => 'Volunteer role'];

    public const STATUSES = ['draft' => 'Draft', 'open' => 'Open', 'closed' => 'Closed', 'filled' => 'Filled', 'removed' => 'Taken down by Hive'];

    protected $fillable = ['startup_uuid', 'title', 'type', 'description', 'requirements', 'skills', 'location', 'remote', 'pay_note', 'closes_on', 'created_by'];

    protected function casts(): array
    {
        return ['skills' => 'array', 'remote' => 'boolean', 'closes_on' => 'date', 'published_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (Vacancy $vacancy) {
            $vacancy->slug ??= Str::limit(Str::slug($vacancy->title), 150, '').'-'.Str::lower(Str::random(6));
        });
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function startup(): BelongsTo
    {
        return $this->belongsTo(Startup::class, 'startup_uuid');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class, 'vacancy_uuid');
    }

    /** Open, not past its closing date, and from an active startup. */
    public function scopeAcceptingApplications(Builder $query): Builder
    {
        return $query->where('status', 'open')
            ->where(fn ($q) => $q->whereNull('closes_on')->orWhere('closes_on', '>=', now()->toDateString()))
            ->whereHas('startup', fn ($q) => $q->where('status', 'active'));
    }

    public function isAcceptingApplications(): bool
    {
        return $this->status === 'open' && (! $this->closes_on || ! $this->closes_on->isPast() || $this->closes_on->isToday())
            && $this->startup?->status === 'active';
    }
}
