<?php

namespace App\Models\Knowledge;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeDownload extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'knowledge_resource_id', 'version', 'kind', 'ip_hash'];

    public function resource(): BelongsTo
    {
        return $this->belongsTo(KnowledgeResource::class, 'knowledge_resource_id');
    }
}
