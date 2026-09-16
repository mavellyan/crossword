<?php

namespace App\Services;

use App\Domain\Crossword\Placement;
use App\Domain\Crossword\PlacementValidationResult;
use App\Enums\Direction;
use App\Enums\ValidationErrors;

class PlacementValidator
{
    /**
     * Validálja a jelölt elhelyezést a meglévő elhelyezésekhez képest. Hibát dob ha a jelölt elhelyezés érvénytelen.
     * Érvénytelen lehet, ha a jelölt elhelyezés:
     *                  - túl hosszú vagy túl rövid választ tartalmaz
     *                  - negatív koordinátán kezdődik
     *                  - a rács határain kívül kezdődik vagy végződik
     *                  - ütközik egy meglévő elhelyezéssel (azonos irányban)
     *                  - ütközik egy meglévő elhelyezéssel (azonos koordinátán, de eltérő betűvel)
     *                  - oldalirányban szomszédos egy meglévő elhelyezéssel (nem metszéspont)
     *                  - nem csatlakozik egy meglévő elhelyezéshez (nincs metszéspont)
     *                  - blokkolt végpontja van (a szó kezdő vagy végpontja egy meglévő elhelyezés mellett van, de nem metszéspont)
     * 
     * @param array<Placement> $existingPlacements A meglévő elhelyezések tömbje.
     * @param Placement $candidatePlacement A jelölt elhelyezés.
     * @param int $maximumRows A rács maximális sorainak száma.
     * @param int $maximumCols A rács maximális oszlopainak száma.
     * @return PlacementValidationResult A validáció eredménye.
     */
    public function validateCandidate(
        array $existingPlacements,
        Placement $candidatePlacement,
        int $maximumRows = 20,
        int $maximumCols = 20,
    ) : PlacementValidationResult
    {
        $errors = [];

        $answerLength = mb_strlen($candidatePlacement->answer);

        if ($answerLength > 20) {
            $errors[] = [
                'code' => ValidationErrors::ANSWER_TOO_LONG,
                'row' => $candidatePlacement->startRow,
                'col' => $candidatePlacement->startCol,
                'message' => 'Answer is too long. It can\'t exceed 20 characters.',
            ];
        }

        if ($answerLength < 2) {
            $errors[] = [
                'code' => ValidationErrors::ANSWER_TOO_SHORT,
                'row' => $candidatePlacement->startRow,
                'col' => $candidatePlacement->startCol,
                'message' => 'Answer is too short. It must be at least 2 characters long.',
            ];
        }

        $candidateCells = $candidatePlacement->cells();

        if ($candidatePlacement->startRow < 0 || $candidatePlacement->startCol < 0) {
            $errors[] = [
                'code' => ValidationErrors::NEGATIVE_COORDINATE,
                'row' => $candidatePlacement->startRow,
                'col' => $candidatePlacement->startCol,
                'message' => 'Placement starts at a negative coordinate.',
            ];
        }

        if ($candidatePlacement->startRow >= $maximumRows || $candidatePlacement->startCol >= $maximumCols) {
            $errors[] = [
                'code' => ValidationErrors::OUT_OF_BOUNDS,
                'row' => $candidatePlacement->startRow,
                'col' => $candidatePlacement->startCol,
                'message' => 'Placement starts outside the grid boundaries.',
            ];
        }

        $endRow = $candidatePlacement->startRow + ($candidatePlacement->direction === Direction::VERTICAL ? $answerLength - 1 : 0);
        $endCol = $candidatePlacement->startCol + ($candidatePlacement->direction === Direction::HORIZONTAL ? $answerLength - 1 : 0);

        if ($endRow >= $maximumRows || $endCol >= $maximumCols) {
            $errors[] = [
                'code' => ValidationErrors::OUT_OF_BOUNDS,
                'row' => $endRow,
                'col' => $endCol,
                'message' => 'Placement ends outside the grid boundaries.',
            ];
        }

        $map = [];

        foreach ($existingPlacements as $placement) {
            foreach ($placement->cells() as $cell) {
                $key = $this->coordinateKey($cell['row'], $cell['col']);

                if (!isset($map[$key])) {
                    $map[$key] = [
                        'letter' => $cell['letter'],
                        'placementIds' => [],
                        'directions' => [],
                    ];
                }
                
                $map[$key]['placementIds'][] = $placement->id;
                $map[$key]['directions'][] = $placement->direction;
            }
        }

        // Vízszintes szó esetén ellenőrizzük a bal és jobb oldali cellákat, függőleges szó esetén a felső és alsó cellákat
        $beforeRow = $candidatePlacement->startRow - ($candidatePlacement->direction === Direction::VERTICAL ? 1 : 0);
        $beforeCol = $candidatePlacement->startCol - ($candidatePlacement->direction === Direction::HORIZONTAL ? 1 : 0);

        $positionBefore = $this->coordinateKey($beforeRow, $beforeCol);

        $afterRow = $candidatePlacement->startRow + ($candidatePlacement->direction === Direction::VERTICAL ? $answerLength : 0);
        $afterCol = $candidatePlacement->startCol + ($candidatePlacement->direction === Direction::HORIZONTAL ? $answerLength : 0);

        $positionAfter = $this->coordinateKey($afterRow, $afterCol);

        
        if (isset($map[$positionBefore])) {
            $errors[] = [
                'code' => ValidationErrors::BLOCKED_ENDPOINT,
                'row' => $beforeRow,
                'col' => $beforeCol,
                'message' => 'Blocked endpoint at ' . $beforeRow . ', col ' . $beforeCol . '.'
            ];
        }

        if (isset($map[$positionAfter])) {
            $errors[] = [
                'code' => ValidationErrors::BLOCKED_ENDPOINT,
                'row' => $afterRow,
                'col' => $afterCol,
                'message' => 'Blocked endpoint at ' . $afterRow . ', col ' . $afterCol . '.'
            ];
        }

        $intersectionCount = 0;

        foreach ($candidateCells as $cell) {
            $key = $this->coordinateKey($cell['row'], $cell['col']);

            // Vízszintes szó esetén a cella feletti cella, függőleges szó esetén a cella bal oldali cellája
            $sidePositionBefore = $candidatePlacement->direction === Direction::HORIZONTAL
                ? $this->coordinateKey($cell['row'] - 1, $cell['col'])
                : $this->coordinateKey($cell['row'], $cell['col'] - 1);

            // Vízszintes szó esetén a cella alatti cella, függőleges szó esetén a cella jobb oldali cellája
            $sidePositionAfter = $candidatePlacement->direction === Direction::HORIZONTAL
                ? $this->coordinateKey($cell['row'] + 1, $cell['col'])
                : $this->coordinateKey($cell['row'], $cell['col'] + 1);

            
            if (isset($map[$key])) {
                if ($map[$key]['letter'] !== $cell['letter']) {
                    $errors[] = [
                        'code' => ValidationErrors::LETTER_CONFLICT,
                        'row' => $cell['row'],
                        'col' => $cell['col'],
                        'message' => 'Letter conflict at row ' . $cell['row'] . ', col ' . $cell['col'] . '.',
                    ];
                }

                if (in_array($candidatePlacement->direction, $map[$key]['directions'], true)) {
                    $errors[] = [
                        'code' => ValidationErrors::SAME_DIRECTION_OVERLAP,
                        'row' => $cell['row'],
                        'col' => $cell['col'],
                        'message' => 'Same direction overlap at row ' . $cell['row'] . ', col ' . $cell['col'] . '.',
                    ];
                }
            }

            // Ha nem metszéspont, és úgy van szomszédos cella, akkor hiba
            if ((isset($map[$sidePositionBefore]) || isset($map[$sidePositionAfter])) && !(isset($map[$key]) && $map[$key]['letter'] === $cell['letter'])) {
                $errors[] = [
                    'code' => ValidationErrors::SIDE_ADJACENCY,
                    'row' => $cell['row'],
                    'col' => $cell['col'],
                    'message' => 'Side adjacency at row ' . $cell['row'] . ', col ' . $cell['col'] . '.',
                ];
            }

            $isOccupied = isset($map[$key]);
            $letterMatches = $isOccupied && $map[$key]['letter'] === $cell['letter'];

            $sameDirection = $isOccupied && in_array($candidatePlacement->direction, $map[$key]['directions'], true);

            $isIntersection = $letterMatches && !$sameDirection;

            if ($isIntersection) {
                $intersectionCount++;
            }
        }

        if ($existingPlacements !== [] && $intersectionCount === 0) {
            $errors[] = [
                'code' => ValidationErrors::DISCONNECTED_ENTRY,
                'row' => $candidatePlacement->startRow,
                'col' => $candidatePlacement->startCol,
                'message' => 'Disconnected entry at row ' . $candidatePlacement->startRow . ', col ' . $candidatePlacement->startCol . '.',
            ];
        }

        return new PlacementValidationResult(
            valid: empty($errors),
            errors: $errors,
            intersectionCount: $intersectionCount,
        );
    }

    private function coordinateKey(int $row, int $col): string
    {
        return $row . ':' . $col;
    }
}