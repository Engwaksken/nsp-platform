<?php

namespace App\Models\Registry;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Durable outbox: events are written in the same transaction as the change
 * and delivered later (Phase 3). Data is minimised: never sensitive values.
 */
class OutboxEvent extends Model
{
    protected $table = 'registry_outbox_events';

    protected $primaryKey = 'event_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = ['event_id', 'type', 'schema_version', 'occurred_at', 'source', 'subject_uuid', 'correlation_id', 'data'];

    protected function casts(): array
    {
        return ['data' => 'array', 'occurred_at' => 'datetime', 'published_at' => 'datetime'];
    }

    public static function record(string $type, string $subjectUuid, array $data = [], ?string $correlationId = null): self
    {
        return self::query()->create([
            'event_id' => (string) Str::uuid(),
            'type' => $type,
            'schema_version' => 1,
            'occurred_at' => now(),
            'source' => 'registry',
            'subject_uuid' => $subjectUuid,
            'correlation_id' => $correlationId,
            'data' => $data,
        ]);
    }
}
