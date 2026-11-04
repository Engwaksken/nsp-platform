<?php

namespace App\Models\Growth;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VgiFramework extends Model
{
    protected $fillable = ['name', 'version', 'status', 'description', 'definition', 'definition_hash', 'approval_note'];

    protected function casts(): array
    {
        return ['definition' => 'array', 'approved_at' => 'datetime', 'version' => 'integer'];
    }

    /** The approved version founders use now (the most recently approved). */
    public static function current(): ?self
    {
        return self::query()->where('status', 'approved')->latest('approved_at')->latest('id')->first();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function questionCount(): int
    {
        return array_sum(array_map(fn ($d) => count($d['questions'] ?? []), $this->definition['dimensions'] ?? []));
    }
}
