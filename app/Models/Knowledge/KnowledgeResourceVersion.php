<?php

namespace App\Models\Knowledge;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeResourceVersion extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['knowledge_resource_id', 'version', 'snapshot', 'file_path', 'file_name', 'file_mime', 'file_size',
        'change_note', 'published_by'];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(KnowledgeResource::class, 'knowledge_resource_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
