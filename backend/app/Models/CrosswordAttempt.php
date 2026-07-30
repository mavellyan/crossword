<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrosswordAttempt extends Model
{
    protected $fillable = [
        'crossword_id',
        'user_id',
        'status',
        'grid_state',
        'state_version',
        'started_at',
        'completed_at',
        'abandoned_at',
    ];

    protected function casts(): array
    {
        return [
            'grid_state' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'abandoned_at' => 'datetime',
        ];
    }

    public function crossword(): BelongsTo
    {
        return $this->belongsTo(Crossword::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}