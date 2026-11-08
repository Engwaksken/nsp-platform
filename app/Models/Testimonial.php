<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Testimonial extends Model
{
    protected $fillable = ['author_name', 'author_role', 'quote', 'photo_media_id', 'consent_confirmed', 'sort_order', 'is_published'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'consent_confirmed' => 'boolean'];
    }

    /** Only quotes the person agreed to publish are shown. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_published', true)->where('consent_confirmed', true)->orderBy('sort_order')->orderBy('id');
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'photo_media_id');
    }
}
