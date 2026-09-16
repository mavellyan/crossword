<?php

namespace App\Domain\Crossword;

use App\Enums\Direction;

final readonly class Placement
{
    public function __construct(
        public ?int $id,
        public int $clueId,
        public string $answer,
        public Direction $direction,
        public int $startRow,
        public int $startCol,
    ) {
    }

    /**
     * Visszaadja az elhelyezéshez tartozó cellák koordinátáit és betűit.
     * 
     * @return array<array{
     *     row: int,
     *     col: int,
     *     letter: string,
     *     index: int
     * }>
     */
    public function cells(): array
    {
        $letters = mb_str_split(mb_strtoupper($this->answer, 'UTF-8'));

        return array_map(function (string $letter, int $index) {
            return [
                'row' => $this->startRow + ($this->direction === Direction::VERTICAL ? $index : 0),
                'col' => $this->startCol + ($this->direction === Direction::HORIZONTAL ? $index : 0),
                'letter' => $letter,
                'index' => $index,
            ];
        }, $letters, array_keys($letters));
    }
}