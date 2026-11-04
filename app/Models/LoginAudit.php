<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginAudit extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'method', 'success', 'reason', 'identifier_hash', 'ip_hash'];

    protected function casts(): array
    {
        return ['success' => 'boolean', 'created_at' => 'datetime'];
    }
}
