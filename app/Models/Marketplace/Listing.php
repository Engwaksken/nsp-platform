<?php

namespace App\Models\Marketplace;

use App\Models\Registry\Startup;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Listing extends Model
{
    use HasUuids;

    protected $table = 'marketplace_listings';

    protected $primaryKey = 'uuid';

    public const KINDS = ['offer' => 'Offer', 'request' => 'Request to buy', 'partnership' => 'Partnership'];

    public const KIND_HINTS = [
        'offer' => 'A product or service your startup sells.',
        'request' => 'Something your startup wants to buy or source (procurement).',
        'partnership' => 'A partner you are looking for: distribution, co-development, pilots and so on.',
    ];

    public const STATUSES = ['draft' => 'Draft', 'published' => 'Published', 'closed' => 'Closed', 'removed' => 'Taken down by Hive'];

    protected $fillable = ['startup_uuid', 'kind', 'category', 'title', 'summary', 'description', 'price_note', 'location', 'expires_on', 'created_by'];

    protected function casts(): array
    {
        return ['expires_on' => 'date', 'published_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (Listing $listing) {
            $listing->slug ??= Str::limit(Str::slug($listing->title), 150, '').'-'.Str::lower(Str::random(6));
        });
    }

    public static function categories(): array
    {
        return config('nsp.marketplace.categories', []);
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

    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class, 'listing_uuid');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('expires_on')->orWhere('expires_on', '>=', now()->toDateString()))
            ->whereHas('startup', fn ($q) => $q->where('status', 'active'));
    }

    public function isLive(): bool
    {
        return $this->status === 'published' && (! $this->expires_on || ! $this->expires_on->isPast() || $this->expires_on->isToday())
            && $this->startup?->status === 'active';
    }
}
