<?php

namespace App\Models\Ecosystem;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Opportunity extends Model
{
    public const TYPES = [
        'grant' => 'Grant',
        'accelerator' => 'Accelerator or incubator',
        'competition' => 'Competition or award',
        'event' => 'Event or training',
        'procurement' => 'Tender or procurement',
        'partnership' => 'Partnership or pilot',
        'investment' => 'Investment call',
        'other' => 'Other',
    ];

    public const STATUSES = ['pending_review', 'draft', 'published', 'rejected', 'archived'];

    protected $fillable = ['type', 'title', 'slug', 'organisation', 'summary', 'body', 'location', 'is_online', 'eligibility',
        'amount_text', 'deadline_at', 'starts_at', 'ends_at', 'apply_url', 'accepts_interest', 'capacity', 'status'];

    protected function casts(): array
    {
        return [
            'eligibility' => 'array', 'is_online' => 'boolean', 'accepts_interest' => 'boolean',
            'deadline_at' => 'datetime', 'starts_at' => 'datetime', 'ends_at' => 'datetime',
            'published_at' => 'datetime', 'reviewed_at' => 'datetime', 'capacity' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Opportunity $opportunity) {
            $opportunity->uuid ??= (string) Str::uuid();
            $opportunity->slug ??= self::uniqueSlug($opportunity->title);
        });
    }

    public static function uniqueSlug(string $title): string
    {
        $base = Str::slug(Str::limit($title, 80, '')) ?: 'opportunity';
        $slug = $base;
        for ($i = 2; self::query()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** Published and either open (deadline ahead or none) or an upcoming event. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('deadline_at')->orWhere('deadline_at', '>=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    public function isOpen(): bool
    {
        return $this->status === 'published'
            && (! $this->deadline_at || $this->deadline_at->isFuture())
            && (! $this->ends_at || $this->ends_at->isFuture());
    }

    public function interests(): HasMany
    {
        return $this->hasMany(OpportunityInterest::class);
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(OpportunityBookmark::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function spacesLeft(): ?int
    {
        if (! $this->capacity) {
            return null;
        }

        return max(0, $this->capacity - $this->interests()->whereIn('status', ['registered', 'confirmed'])->count());
    }
}
