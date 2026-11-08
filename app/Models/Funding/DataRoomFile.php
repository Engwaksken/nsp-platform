<?php

namespace App\Models\Funding;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** A private document. Stored on the non-public disk and only served through an authorised download. */
class DataRoomFile extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';

    public const CATEGORIES = ['pitch_deck' => 'Pitch deck', 'financials' => 'Financial statements', 'projections' => 'Financial projections',
        'legal' => 'Legal and registration', 'traction' => 'Traction and customers', 'other' => 'Other'];

    protected $fillable = ['startup_uuid', 'title', 'category', 'disk', 'path', 'original_name', 'mime_type', 'size', 'uploaded_by'];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function booted(): void
    {
        static::deleted(fn (DataRoomFile $file) => Storage::disk($file->disk)->delete($file->path));
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
