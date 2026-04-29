<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\Difficulty;

class Crossword extends Model
{
    protected $fillable = [
        'title',
        'creator_user_id',
        'main_solution',
        'difficulty',
        'is_public',
    ];

    protected $casts = [
        'difficulty' => Difficulty::class,
        'is_public' => 'boolean',
    ];

    public function crosswordClues(): HasMany
    {
        return $this->hasMany(CrosswordClue::class);
    }

    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_user_id');
    }
}
