<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyHealthSummary extends Model
{
    protected $fillable = ['user_id', 'date', 'weight_kg', 'body_fat_percent', 'sleep_hours'];

    protected $casts = [
        'date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
