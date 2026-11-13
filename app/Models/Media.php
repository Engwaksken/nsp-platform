<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Media extends Model
{
    protected $table = 'media';

    protected $fillable = ['disk', 'path', 'original_name', 'mime_type', 'size', 'alt_text', 'uploaded_by'];

    protected static function booted(): void
    {
        static::creating(fn (Media $media) => $media->uuid ??= (string) Str::uuid());
        static::deleted(fn (Media $media) => Storage::disk($media->disk)->delete($media->path));
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function isVideo(): bool
    {
        return str_starts_with($this->mime_type, 'video/');
    }
}
