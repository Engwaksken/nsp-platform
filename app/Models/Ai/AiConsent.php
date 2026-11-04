<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;

class AiConsent extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'version', 'granted_at', 'revoked_at'];

    protected function casts(): array
    {
        return ['granted_at' => 'datetime', 'revoked_at' => 'datetime'];
    }
}
