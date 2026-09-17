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

    /**
     * Validálja a teljes elrendezést a megadott elhelyezések alapján.
     * Megnézi, hogy van-e legalább 2 elhelyezés, majd egy szomszédsági mátrixot hoz létre az elhelyezések között,
     * és mélységi kereséssel ellenőrzi, hogy az összes elhelyezés összekapcsolódik-e.
     * 
     * @param array<Placement> $placements A teljes elrendezés elhelyezései.
     * @param int $maximumRows A rács maximális sorainak száma
     * @param int $maximumCols A rács maximális oszlopainak száma
     * @return PlacementValidationResult A validáció eredménye.
     */
    public function validateLayout(
        array $placements,
        int $maximumRows = 20,
        int $maximumCols = 20,
    ): PlacementValidationResult
    {
        $errors = [];

        if (count($placements) < 2) {
            $errors[] = [
                'code' => ValidationErrors::TOO_FEW_ENTRIES,
                'message' => 'There must be at least 2 entries in the layout.',
            ];
        }

        foreach ($placements as $placement) {
            $candidateResult = $this->validateCandidate(
                existingPlacements: array_filter($placements, fn($p) => $p !== $placement),
                candidatePlacement: $placement,
                maximumRows: $maximumRows,
                maximumCols: $maximumCols,
            );

            if (!$candidateResult->valid) {
                $errors = array_merge($errors, $candidateResult->errors);
            }
        }

        $adjacencyMap = [];

        // Létrehozunk egy szomszédsági tömböt minden elhelyezésnek egy üres tömbbel
        foreach ($placements as $index => $placement) {
            $adjacencyMap[$index] = [];
        }

        $intersectionCount = 0;

        // Végigmegyünk az összes elhelyezés páron, úgy, hogy minden pár csak egyszer legyen ellenőrizve, 
        // azaz, ha a jobbIndex kisebb vagy egyenlő a balIndex-szel, akkor kihagyjuk, pl. indexek: (0,1), (0,2), (0,3), (1,2), (1,3), (2,3) stb.,
        // így nem lesz (0,1) és (1,0) pár (ugyanaz csak más sorrendben), illetve (1,1) pár sem (önmaga).
        // Ha két elhelyezés metszi egymást, akkor hozzáadjuk az adott index szomszédsági tömbjéhez a másik indexet.
        foreach ($placements as $leftIndex => $left) {
            foreach ($placements as $rightIndex => $right) {
                if ($rightIndex <= $leftIndex) {
                    continue;
                }

                if ($this->placementsIntersect($left, $right)) {
                    $intersectionCount++;
                    $adjacencyMap[$leftIndex][] = $rightIndex;
                    $adjacencyMap[$rightIndex][] = $leftIndex;
                }
            }
        }

        $visited = [];
        $stack = [0];

        // Ezután egy mélységi keresés algoritmust használunk, hogy ellenőrizzük, hogy az összes elhelyezés összekapcsolódik-e.
        while ($stack !== []) {
            // Kivesszük a legutolsó indexet a veremből
            $index = array_pop($stack);

            // Megnézzük, hogy az index már látogatott-e, ha igen, akkor kihagyjuk
            if (isset($visited[$index])) {
                continue;
            }

            // Ha még nem látogatott, akkor jelöljük meg látogatottnak
            $visited[$index] = true;

            // Hozzáadjuk az összes szomszédját a veremhez, hogy később ellenőrizzük őket
            foreach ($adjacencyMap[$index] as $neighbor) {
                $stack[] = $neighbor;
            }
        }

        // Ha a látogatott elhelyezések száma nem egyezik meg az összes elhelyezés számával, akkor az elrendezés nem jó, nincs rendesen összekapcsolva
        if (count($visited) !== count($placements)) {
            $errors[] = [
                'code' => ValidationErrors::DISCONNECTED_LAYOUT,
                'message' => 'The layout is disconnected. All placements must be connected.',
            ];
        }

        return new PlacementValidationResult(
            valid: empty($errors),
            errors: $errors,
            intersectionCount: $intersectionCount,
        );
    }

    /**
     * Ellenőrzi, hogy két elhelyezés metszik-e egymást. Két elhelyezés akkor metszi egymást,
     * ha van legalább egy cellájuk, amely ugyanazon a soron és oszlopon van, ugyanaz a betűjük, és az irányuk különböző (azaz az egyik vízszintes, a másik függőleges).
     * 
     * @param Placement $a Az első elhelyezés.
     * @param Placement $b A második elhelyezés.
     * @return bool Igaz, ha a két elhelyezés metszi egymást, hamis egyébként.
     */
    private function placementsIntersect(Placement $a, Placement $b): bool
    {
        $aCells = $a->cells();
        $bCells = $b->cells();

        foreach ($aCells as $aCell) {
            foreach ($bCells as $bCell) {
                if (
                    $aCell['row'] === $bCell['row'] &&
                    $aCell['col'] === $bCell['col'] &&
                    $aCell['letter'] === $bCell['letter'] &&
                    $a->direction !== $b->direction
                ) {
                    return true;
                }
            }
        }

        return false;
    }
}