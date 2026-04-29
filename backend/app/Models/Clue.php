<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Clue extends Model
{
    protected $fillable = [
        'definition',
        'solution',
    ];

    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class);
    }

    public function placements(): HasMany
    {
        return $this->hasMany(CrosswordClue::class);
    }
}
