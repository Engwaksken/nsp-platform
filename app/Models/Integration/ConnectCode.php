<?php

namespace App\Models\Integration;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConnectCode extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['code_hash', 'api_client_id', 'startup_uuid', 'user_id', 'redirect_uri', 'expires_at', 'used_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
