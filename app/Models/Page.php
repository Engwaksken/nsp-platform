<?php

namespace App\Models;

use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Page extends Model
{
    use Publishable, SoftDeletes;

    public const TEMPLATES = ['default' => 'Standard page', 'landing' => 'Landing page (no sidebar)'];

    /** Section types the page builder supports. */
    public const BLOCK_TYPES = [
        'hero' => 'Hero banner',
        'text' => 'Text',
        'stats' => 'Impact numbers',
        'cta' => 'Call to action',
        'testimonials' => 'Testimonials',
        'partners' => 'Partners',
        'faqs' => 'FAQs',
        'latest_posts' => 'Latest news',
    ];

    /** Slugs that clash with application routes. */
    public const RESERVED_SLUGS = ['admin', 'login', 'logout', 'auth', 'workspace', 'news', 'opportunities', 'connect', 'invitations', 'account', 'faqs', 'contact', 'storage', 'up', 'sitemap.xml', 'robots.txt', 'api'];

    protected $fillable = ['title', 'slug', 'template', 'summary', 'blocks', 'status', 'published_at',
        'seo_title', 'seo_description', 'og_image_id', 'locale'];

    protected function casts(): array
    {
        return ['blocks' => 'array', 'published_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (Page $page) => $page->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PageRevision::class)->latest('id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function ogImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'og_image_id');
    }

    public function publicUrl(): string
    {
        return $this->slug === 'home' ? url('/') : url($this->slug);
    }

    public function snapshot(?int $userId, ?string $note = null): PageRevision
    {
        return $this->revisions()->create([
            'title' => $this->title,
            'summary' => $this->summary,
            'blocks' => $this->blocks,
            'meta' => [
                'slug' => $this->slug, 'template' => $this->template, 'status' => $this->status,
                'published_at' => $this->published_at?->toIso8601String(),
                'seo_title' => $this->seo_title, 'seo_description' => $this->seo_description,
            ],
            'note' => $note,
            'created_by' => $userId,
        ]);
    }
}
