<?php

namespace App\Models\Ecosystem;

use Illuminate\Database\Eloquent\Model;

class RecommendationFeedback extends Model
{
    protected $table = 'recommendation_feedback';

    protected $fillable = ['user_id', 'startup_uuid', 'item_type', 'item_id', 'feedback'];
}
