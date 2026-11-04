<?php

namespace App\Models\Integration;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InboxEvent extends Model
{
    protected $table = 'integration_inbox';

    protected $primaryKey = 'event_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = ['event_id', 'api_client_id', 'type', 'subject_uuid', 'status', 'error', 'data', 'occurred_at', 'received_at'];

    protected function casts(): array
    {
        return ['data' => 'array', 'occurred_at' => 'datetime', 'received_at' => 'datetime'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class, 'api_client_id');
    }
}
