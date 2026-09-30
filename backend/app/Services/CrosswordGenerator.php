<?php

namespace App\Services;

use App\Enums\Direction;
use App\Models\Clue;
use App\Models\CrosswordClue;
use Exception;
use Illuminate\Support\Collection;
use LogicException;

class CrosswordGenerator
{
    /**
     * A rásegítéses rejtvény készítés esetén ez rendeli a szavakat a főmegoldás betűihez.
     *
     * @param string $mainSolution
     * @param Collection<int, Clue>|array<int, Clue> $clues
     * @return array<int, array<string, mixed>>
     * @throws Exception
     */
    public function generatePlacementsFixedOrder(string $mainSolution, Collection|array $clues): array
    {
        $clues = collect($clues)->values();

        $mainSolution = mb_strtoupper($mainSolution);
        $mainLetters = mb_str_split($mainSolution);

        if ($clues->isEmpty()) {
            throw new Exception('Nem adtál meg szavakat.');
        }

        if ($clues->count() !== count($mainLetters)) {
            throw new Exception('A szavak számának meg kell egyeznie a főmegoldás hosszával.');
        }

        $matches = [];

        foreach ($clues as $index => $clue) {
            $mainLetter = $mainLetters[$index];
            $solution = mb_strtoupper($clue->solution);

            $intersectionIndex = mb_strpos($solution, $mainLetter);

            if ($intersectionIndex === false) {
                throw new Exception(
                    'A(z) "' . $clue->solution . '" szó nem tartalmazza a főmegoldás ' .
                    ($index + 1) . '. betűjét: ' . $mainLetter
                );
            }

            $matches[] = [
                'clue_id' => $clue->id,
                'row' => $index,
                'offset' => $intersectionIndex,
            ];
        }

        $placements = [];

        $solutionCol = max(array_column($matches, 'offset'));

        foreach ($matches as $index => $match) {
            $placements[$index] = [
                'clue_id' => $match['clue_id'],
                'direction' => Direction::HORIZONTAL,
                'start_row' => $match['row'],
                'start_col' => $solutionCol - $match['offset'],
                'is_main' => false,
            ];
        }

        return $placements;
    }

    /**
     * Elkészíti a rácsot a már elmentett CrosswordClue sorokból.
     *
     * @param iterable<int, CrosswordClue> $placements
     * @return array<string, mixed>
     */
    public function generateGrid(iterable $placements): array
    {
        $placements = collect($placements)
            ->where('is_main', false)
            ->values();

        $height = 0;
        $width = 0;

        foreach ($placements as $placement) {
            $solution = mb_strtoupper($placement->getSolution());
            $direction = $placement->getDirection();

            if ($direction === Direction::HORIZONTAL->value) {
                $height = max($height, $placement->getStartRow() + 1);
                $width = max($width, $placement->getStartCol() + mb_strlen($solution));
            }

            if ($direction === Direction::VERTICAL->value) {
                $height = max($height, $placement->getStartRow() + mb_strlen($solution));
                $width = max($width, $placement->getStartCol() + 1);
            }
        }

        $grid = array_fill(0, $height, array_fill(0, $width, '#'));

        foreach ($placements as $placement) {
            $solution = mb_strtoupper($placement->getSolution());
            $direction = $placement->getDirection();

            for ($i = 0; $i < mb_strlen($solution); $i++) {
                $row = $placement->getStartRow()
                    + ($direction === Direction::VERTICAL->value ? $i : 0);

                $col = $placement->getStartCol()
                    + ($direction === Direction::HORIZONTAL->value ? $i : 0);
                
                $letter = mb_substr($solution, $i, 1);

                if ($grid[$row][$col] !== '#' && $grid[$row][$col] !== $letter) {
                    throw new LogicException("A rácsban ütközés történt a {$row}, {$col} koordinátánál.");
                }

                $grid[$row][$col] = $letter;
            }
        }

        $publicGrid = array_map(fn (array $row) => array_map(
            fn (string $cell) => $cell === '#' ? '#' : null,
            $row
        ), $grid);

        return [
            'grid' => $publicGrid,
            'width' => $width,
            'height' => $height,
        ];
    }

    private function assignWordsToLetters(
        array $letters,
        Collection $clues,
        int $letterIndex = 0,
        array $usedClueIndexes = [],
        array $verticalPositions = [],
        array $horizontalPositions = [],
    ): array {
        if ($letterIndex >= count($letters)) {
            return [
                'valid' => true,
                'vertical_positions' => $verticalPositions,
                'horizontal_positions' => $horizontalPositions,
            ];
        }

        $currentLetter = $letters[$letterIndex];

        foreach ($clues as $clueIndex => $clue) {
            if (in_array($clueIndex, $usedClueIndexes, true)) {
                continue;
            }

            $solution = mb_strtoupper($clue->solution);
            $position = mb_strpos($solution, $currentLetter);

            if ($position === false) {
                continue;
            }

            $newUsedClueIndexes = [...$usedClueIndexes, $clueIndex];

            $newVerticalPositions = $verticalPositions;
            $newHorizontalPositions = $horizontalPositions;

            $newVerticalPositions[$clueIndex] = $letterIndex;
            $newHorizontalPositions[$clueIndex] = $position;

            $result = $this->assignWordsToLetters(
                $letters,
                $clues,
                $letterIndex + 1,
                $newUsedClueIndexes,
                $newVerticalPositions,
                $newHorizontalPositions,
            );

            if ($result['valid']) {
                return $result;
            }
        }

        return [
            'valid' => false,
            'vertical_positions' => [],
            'horizontal_positions' => [],
        ];
    }
}