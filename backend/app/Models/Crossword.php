<?php

namespace App\Models;

use App\Enums\Difficulty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Crossword extends Model
{
    use SoftDeletes, HasFactory;

    protected $fillable = [
        'title',
        'user_id',
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
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getWords()
    {
        if (!$this->relationLoaded('crosswordClues')) {
            $this->load('crosswordClues.clue');
        }

        return $this->crosswordClues
            ->sortBy('start_row')
            ->values();
    }

    /**
     * A rejtvényhez tartozó próbálkozások lekérése.
     */    
    public function attempts()
    {
        return $this->hasMany(CrosswordAttempt::class);
    }
}