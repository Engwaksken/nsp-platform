<?php

namespace App\Models\Registry;

use Illuminate\Database\Eloquent\Model;

/** Maps an ID from another system (for example an IMS application) to the canonical startup. */
class StartupAlias extends Model
{
    public const SOURCES = ['ims' => 'Hive IMS', 'nsp' => 'NSP', 'other' => 'Other'];

    protected $fillable = ['source_system', 'external_id', 'startup_uuid', 'created_by'];
}
