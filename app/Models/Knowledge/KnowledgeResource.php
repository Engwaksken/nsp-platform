<?php

namespace App\Models\Knowledge;

use App\Knowledge\KnowledgeAccess;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class KnowledgeResource extends Model
{
    public const STATUSES = ['draft' => 'Draft', 'in_review' => 'In review', 'published' => 'Published', 'archived' => 'Archived'];

    protected $fillable = ['slug', 'title', 'summary', 'body', 'resource_type', 'category_id', 'author', 'source', 'publisher',
        'publication_date', 'license', 'external_url', 'visibility', 'audience', 'stages', 'vgi_dimension_keys', 'builder_step_keys',
        'review_due_at', 'expires_at', 'seo_title', 'seo_description'];

    protected $hidden = ['file_path', 'file_disk'];

    protected function casts(): array
    {
        return [
            'publication_date' => 'date',
            'audience' => 'array',
            'stages' => 'array',
            'vgi_dimension_keys' => 'array',
            'builder_step_keys' => 'array',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
            'review_due_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (KnowledgeResource $resource) => $resource->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(KnowledgeCategory::class, 'category_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(KnowledgeTag::class, 'knowledge_resource_tag');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(KnowledgeResourceVersion::class)->orderByDesc('version');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Published, already live, and not expired. */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /** Restricts to resources the user may open. Always applied before any search ranking. */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        return $query->whereIn('visibility', KnowledgeAccess::visibilitiesFor($user));
    }

    public function isLive(): bool
    {
        return $this->status === 'published' && $this->published_at !== null && $this->published_at->lte(now())
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->lte(now());
    }

    /** Content review date has passed: show a warning that it may be out of date. */
    public function isStale(): bool
    {
        return $this->review_due_at !== null && $this->review_due_at->lte(now());
    }

    public function hasFile(): bool
    {
        return $this->file_path !== null;
    }

    public function isLinkOnly(): bool
    {
        return ! $this->hasFile() && $this->external_url !== null;
    }

    public function typeLabel(): string
    {
        return config('knowledge.resource_types.'.$this->resource_type, Str::headline($this->resource_type));
    }

    public function licenseLabel(): string
    {
        return config('knowledge.licenses.'.$this->license.'.label', $this->license);
    }

    public function visibilityLabel(): string
    {
        return config('knowledge.visibilities.'.$this->visibility, $this->visibility);
    }

    public function statusLabel(): string
    {
        if ($this->status === 'published' && $this->isExpired()) {
            return 'Expired';
        }

        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** Labels for a JSON list column using a config map (audiences, stages, vgi_dimensions). */
    public function labelsFor(string $column, string $configKey): array
    {
        $map = config('knowledge.'.$configKey, []);

        return array_values(array_map(fn ($key) => $map[$key] ?? Str::headline((string) $key), (array) ($this->{$column} ?? [])));
    }

    /** Human-friendly file size. */
    public function fileSizeLabel(): ?string
    {
        if (! $this->file_size) {
            return null;
        }

        return $this->file_size >= 1048576 ? round($this->file_size / 1048576, 1).' MB' : max(1, (int) round($this->file_size / 1024)).' KB';
    }
}
