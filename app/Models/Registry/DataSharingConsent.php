<?php

namespace App\Models\Registry;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataSharingConsent extends Model
{
    public $timestamps = false;

    protected $fillable = ['startup_uuid', 'purpose', 'recipient_system', 'scope', 'granted_by', 'granted_at', 'revoked_by', 'revoked_at', 'policy_version'];

    protected function casts(): array
    {
        return ['scope' => 'array', 'granted_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function granter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }
}
