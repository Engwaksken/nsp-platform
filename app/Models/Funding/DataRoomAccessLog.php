<?php

namespace App\Models\Funding;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One row per data-room download, shown to the startup's team. */
class DataRoomAccessLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['file_uuid', 'startup_uuid', 'user_id', 'access_type', 'ip_hash', 'accessed_at'];

    protected function casts(): array
    {
        return ['accessed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(DataRoomFile::class, 'file_uuid');
    }
}
