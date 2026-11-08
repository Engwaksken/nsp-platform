<?php

namespace App\Models\Knowledge;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Bookkeeping for a future semantic index: what needs (re)indexing, and under which access scope. */
class KnowledgeIndexMetadata extends Model
{
    protected $table = 'knowledge_index_metadata';

    protected $fillable = ['knowledge_resource_id', 'index_name', 'visibility', 'version', 'checksum', 'indexed_at'];

    protected function casts(): array
    {
        return ['indexed_at' => 'datetime'];
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(KnowledgeResource::class, 'knowledge_resource_id');
    }
}
