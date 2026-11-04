<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItem extends Model
{
    protected $fillable = ['label', 'url', 'page_id', 'parent_id', 'sort_order', 'open_in_new_tab'];

    protected function casts(): array
    {
        return ['open_in_new_tab' => 'boolean'];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function href(): string
    {
        return $this->page ? $this->page->publicUrl() : (string) $this->url;
    }
}
