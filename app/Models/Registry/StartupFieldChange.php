<?php

namespace App\Models\Registry;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Change history and field provenance. Sensitive values are never stored here, only that they changed. */
class StartupFieldChange extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['startup_uuid', 'field_name', 'old_value', 'new_value', 'source_system', 'changed_by', 'version'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
