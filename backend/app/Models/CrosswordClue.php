<?php

namespace App\Models;

use App\Enums\Direction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    protected $appends = [
        'definition',
        'solution',
        'length',
        'cells',
    ];

    public function crossword(): BelongsTo
    {
        return $this->belongsTo(Crossword::class);
    }

    public function clue(): BelongsTo
    {
        return $this->belongsTo(Clue::class);
    }

    public function getDefinitionAttribute(): ?string
    {
        return $this->clue?->definition;
    }

    public function getSolutionAttribute(): ?string
    {
        return $this->clue?->solution;
    }

    public function getLengthAttribute(): int
    {
        return mb_strlen((string) $this->solution);
    }

    /**
     * @return int
     */
    public function getStartCol(): int
    {
        return (int) $this->start_col;
    }

    /**
     * @return int
     */
    public function getStartRow(): int
    {
        return (int) $this->start_row;
    }
    
    /**
     * @param int $startCol
     * @return void
     */
    public function setStartCol(int $startCol): void
    {
        $this->start_col = $startCol;
    }

    /**
     * @param int $startRow
     * @return void
     */
    public function setStartRow(int $startRow): void
    {
        $this->start_row = $startRow;
    }

    /**
    * @return int
    */
    public function getIntersectionIndex(): int
    {
        return (int) $this->intersection_index;
    }

    /**
     * @param int $intersectionIndex
     * @return void
     */
    public function setIntersectionIndex(int $intersectionIndex): void
    {
        $this->intersection_index = $intersectionIndex;
    }

    /**
     * @return string
     */
    public function getSolution(): string
    {
        return (string) $this->solution;
    }

    /**
     * @return string
     */
    public function getDefinition(): string
    {
        return (string) $this->definition;
    }

    /**
     * @return bool
     */
    public function isMain(): bool
    {
        return (bool) $this->is_main;
    }

    /**
     * @param bool $isMain
     * @return void
     */
    public function setMain(bool $isMain): void
    {
        $this->is_main = $isMain;
    }

    /**
     * @return string
     */
    public function getDirection(): string
    {
        if ($this->direction instanceof Direction) {
            return $this->direction->value;
        }

        return (string) $this->direction;
    }

    /**
     * @param Direction $direction
     * @return void
     */
    public function setDirection(Direction|string $direction): void
    {
        $this->direction = $direction instanceof Direction
            ? $direction
            : Direction::from($direction);
    }

    public function getCellsAttribute(): array
    {
        return $this->getCells();
    }

    /**
     * Visszaadja a megoldás betűit és pozícióit egy tömbben, hogy megkönnyítse a rácsban való elhelyezésüket
     * 
     * @return array
     */
    public function getCells(): array
    {
        $cells = [];
        $solution = mb_strtoupper((string) $this->solution);

        for ($i = 0; $i < mb_strlen($solution); $i++) {
            $cells[] = [
                'letter' => mb_substr($solution, $i, 1),
                'row' => $this->getStartRow() + ($this->getDirection() === 'vertical' ? $i : 0),
                'col' => $this->getStartCol() + ($this->getDirection() === 'horizontal' ? $i : 0),
            ];
        }

        return $cells;
    }

    /**
     * Debug segítség, minta:
     * Tanulóidőszak: inasév (általános, 6) (direction: horizontal, x:1, y:1)
     *
     * @return string
     */
    public function getDebug(): string
    {
        return $this->definition . ': ' .
            $this->solution . ' (' .
            $this->length . ') ' .
            '(direction: ' . $this->getDirection() .
            ', x: ' . $this->getStartCol() .
            ', y: ' . $this->getStartRow() . ')';
    }
}