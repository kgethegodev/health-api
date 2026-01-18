<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserGoal extends Model
{
    protected $fillable = ['user_id', 'goal', 'weight', 'body_fat_percent', 'starts_at'];

    public function user(): belongsTo
    {
        return $this->belongsTo(User::class);
    }
}
