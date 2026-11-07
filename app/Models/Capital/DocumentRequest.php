<?php

namespace App\Models\Capital;

use App\Models\Funding\DataRoomFile;
use App\Models\Registry\Startup;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An investor asks for a document; the founders add it to the data room or decline. */
class DocumentRequest extends Model
{
    public const STATUSES = ['requested' => 'Requested', 'fulfilled' => 'Added to data room', 'declined' => 'Declined'];

    protected $table = 'capital_document_requests';

    protected $fillable = ['introduction_uuid', 'startup_uuid', 'investor_user_id', 'title', 'category', 'message', 'status',
        'data_room_file_uuid', 'response_note', 'responded_by', 'responded_at'];

    protected function casts(): array
    {
        return ['responded_at' => 'datetime'];
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
