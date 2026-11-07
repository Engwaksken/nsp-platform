<?php

namespace App\Models\Capital;

use App\Models\Funding\DataRoomFile;
use App\Models\Funding\Introduction;
use App\Models\Registry\Startup;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One due-diligence checklist item for an introduction. Status and notes are visible to both sides. */
class DiligenceItem extends Model
{
    protected $table = 'due_diligence_items';

    protected $fillable = ['introduction_uuid', 'startup_uuid', 'investor_user_id', 'title', 'category', 'status', 'due_date', 'notes',
        'data_room_file_uuid', 'created_by', 'updated_by', 'reminded_at'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'reminded_at' => 'datetime'];
    }

    /** @return array<string, string> */
    public static function statuses(): array
    {
        return config('capital.diligence_statuses', []);
    }

    public function statusLabel(): string
    {
        return self::statuses()[$this->status] ?? $this->status;
    }

    public function categoryLabel(): string
    {
        return (string) config('capital.diligence_categories.'.$this->category, $this->category);
    }

    public function introduction(): BelongsTo
    {
        return $this->belongsTo(Introduction::class, 'introduction_uuid');
    }

    public function startup(): BelongsTo
    {
        return $this->belongsTo(Startup::class, 'startup_uuid');
    }

    public function investor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'investor_user_id');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(DataRoomFile::class, 'data_room_file_uuid');
    }
}
