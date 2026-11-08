<?php

namespace App\Models\Registry;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class StartupFounder extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';

    protected $fillable = ['startup_uuid', 'user_id', 'name', 'title', 'visibility', 'sort_order'];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }
}
