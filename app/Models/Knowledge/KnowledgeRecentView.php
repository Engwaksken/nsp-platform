<?php

namespace App\Models\Knowledge;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeRecentView extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'knowledge_resource_id', 'viewed_at'];

    protected function casts(): array
    {
        return ['viewed_at' => 'datetime'];
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(KnowledgeResource::class, 'knowledge_resource_id');
    }
}
