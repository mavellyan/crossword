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
     * AUTOMATA ELHELYEZÉS
     * 
     * Kiszámolja, hogy a clue-k hova kerüljenek a rejtvényben. Ha a user nem kézileg akarja elrendezni a szavakat, hanem a rendszerre bízza.
     *
     * @param string $mainSolution
     * @param Collection<int, Clue>|array<int, Clue> $clues
     * @return array<int, array<string, mixed>>
     * @throws Exception
     */
    public function generatePlacementsAutomatically(string $mainSolution, Collection|array $clues): array
    {
        $clues = collect($clues)->values();
        $mainSolution = mb_strtolower($mainSolution);
        $mainLetters = mb_str_split(mb_strtoupper($mainSolution));

        if ($clues->isEmpty()) {
            throw new Exception('Nem adtál meg szavakat.');
        }

        if ($clues->count() !== count($mainLetters)) {
            throw new Exception('A szavak számának meg kell egyeznie a főmegoldás hosszával.');
        }

        $assignment = $this->assignWordsToLetters($mainLetters, $clues);

        if (!$assignment['valid']) {
            throw new Exception('A megadott szavakból nem állítható össze rejtvény.');
        }

        $solutionCol = max($assignment['horizontal_positions']);

        $placements = [];

        foreach ($clues as $index => $clue) {
            $intersectionIndex = $assignment['horizontal_positions'][$index];
            $row = $assignment['vertical_positions'][$index];
            $col = $solutionCol - $intersectionIndex;

            $placements[] = [
                'clue_id' => $clue->id,
                'direction' => Direction::HORIZONTAL,
                'start_row' => $row,
                'start_col' => $col,
                'intersection_index' => $intersectionIndex,
                'is_main' => false,
            ];
        }

        usort($placements, function (array $a, array $b) {
            return $a['start_row'] <=> $b['start_row'];
        });

        return $placements;
    }

    /**
     * FIX SORRENDES ELHELYEZÉS
     *
     * Ezt használja a jelenlegi CrosswordCreator.
     * A frontend által küldött szavak sorrendje megmarad:
     * selectedWords[0] -> mainSolution[0]
     * selectedWords[1] -> mainSolution[1]
     * stb.
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

        $placements = [];

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

            $placements[] = [
                'clue_id' => $clue->id,
                'direction' => Direction::HORIZONTAL,
                'start_row' => $index,
                'intersection_index' => $intersectionIndex,
                'is_main' => false,
            ];
        }

        $solutionCol = max(array_column($placements, 'intersection_index'));

        foreach ($placements as $index => $placement) {
            $placements[$index]['start_col'] = $solutionCol - $placement['intersection_index'];
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