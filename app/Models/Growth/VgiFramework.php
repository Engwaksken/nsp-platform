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
        return ['definition' => 'array', 'approved_at' => 'datetime', 'version' => 'integer',
            'owner_signed_on' => 'date', 'owner_signoff_recorded_at' => 'datetime'];
    }

    public const METHODOLOGY = ['provisional' => 'Provisional methodology', 'owner_approved' => 'Owner-approved methodology'];

    /**
     * Hive approval makes a version usable; only a recorded owner sign-off makes
     * its methodology official. Until then stages and readiness are provisional.
     */
    public function isProvisional(): bool
    {
        return ($this->methodology_status ?? 'provisional') !== 'owner_approved';
    }

    public function methodologyLabel(): string
    {
        return self::METHODOLOGY[$this->methodology_status ?? 'provisional'] ?? self::METHODOLOGY['provisional'];
    }

    public function signoffRecorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_signoff_recorded_by');
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
