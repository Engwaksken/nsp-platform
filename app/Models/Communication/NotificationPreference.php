<?php

namespace App\Models\Communication;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    protected $fillable = ['user_id', 'type', 'email', 'in_app'];

    protected function casts(): array
    {
        return ['email' => 'boolean', 'in_app' => 'boolean'];
    }
}
