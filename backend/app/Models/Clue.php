<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Clue extends Model
{
    use HasFactory;

    protected $fillable = [
        'definition',
        'solution',
    ];

    protected $appends = [
        'length',
        'topic_name',
    ];

    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class);
    }

    public function placements(): HasMany
    {
        return $this->hasMany(CrosswordClue::class);
    }

    public function getLengthAttribute(): int
    {
        return mb_strlen((string) $this->solution);
    }

    public function getTopicNameAttribute(): string
    {
        if (!$this->relationLoaded('topics')) {
            return 'általános';
        }

        $topics = $this->topics
            ->pluck('name')
            ->filter()
            ->implode(', ');

        return $topics !== '' ? $topics : 'általános';
    }

    /**
     * @return int
     */
    public function getLength(): int
    {
        return $this->length;
    }

    /**
     * @return string
     */
    public function getTopic(): string
    {
        return $this->topic_name;
    }

    /**
     * @return string
     */
    public function getDefinition(): string
    {
        return $this->definition;
    }

    /**
     * @return string
     */
    public function getSolution(): string
    {
        return $this->solution;
    }

    /**
     * Debug segítség, minta:
     * Tanulóidőszak: inasév (általános, 6)
     *
     * @return string
     */
    public function getDebug(): string
    {
        return $this->definition . ': ' .
            $this->solution . ' (' .
            $this->getTopic() . ', ' .
            $this->getLength() . ')';
    }
}