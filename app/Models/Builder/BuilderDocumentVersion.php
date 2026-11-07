<?php

namespace App\Models\Builder;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuilderDocumentVersion extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['document_id', 'number', 'source', 'body', 'created_by'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'number' => 'integer'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
