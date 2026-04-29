<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Topic extends Model
{
    protected $fillable = [
        'name',
        'description',
    ];

    public function clues(): BelongsToMany
    {
        return $this->belongsToMany(Clue::class);
    }

    public function crosswords(): BelongsToMany
    {
        return $this->belongsToMany(Crossword::class);
    }
}
