<?php

namespace App\Models\Challenges;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Immutable snapshot of a challenge's content at submission. Only approval/publication stamps may be added later. */
class ChallengeVersion extends Model
{
    /** Columns that may change after the snapshot is written. */
    private const STAMPS = ['approved_by', 'approved_at', 'published_by', 'published_at', 'updated_at'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['content' => 'array', 'version' => 'integer', 'approved_at' => 'datetime', 'published_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (ChallengeVersion $version) {
            $changed = array_diff(array_keys($version->getDirty()), self::STAMPS);
            if ($changed !== []) {
                throw new LogicException('Challenge versions are immutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Challenge versions cannot be deleted.'));
    }

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function field(string $key): mixed
    {
        return $this->content[$key] ?? null;
    }
}
