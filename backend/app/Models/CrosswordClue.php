<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\Direction;

class CrosswordClue extends Model
{
    protected $fillable = [
        'crossword_id',
        'clue_id',
        'direction',
        'intersection_index',
        'start_row',
        'start_col',
        'is_main',
    ];

    protected $casts = [
        'is_main' => 'boolean',
        'direction' => Direction::class,
    ];

    public function crossword(): BelongsTo
    {
        return $this->belongsTo(Crossword::class);
    }

    public function clue(): BelongsTo
    {
        return $this->belongsTo(Clue::class);
    }
}
