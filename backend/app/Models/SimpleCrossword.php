<?php

namespace App\Models;

use App\Models\Crossword;
use App\Enums\Direction;
use Exception;

class SimpleCrossword extends Crossword {
    /**
     * Beállítja a rejtvény szavait, levizsgálva, hogy lehetséges-e belőlük rejtvényt alkotni
     *
     * @param array $words
     * @return void
     * @throws Exception
     */
    #[\Override]
    public function setWords(array $words): void
    {
        if (empty($words)) {
            throw new Exception("Üres tömb átadva?");
        }

        if (count($words) > $this->getMainSolution()->getLength()) {
            throw new Exception("Nem egyezik a szavak száma és a főmegoldás hossza!");
        }

        foreach ($words as $word) {
            if (!$word instanceof Clue) {
                throw new Exception("A tömbbe bekerült valami, ami nem Clue típusú?");
            }
        }

        $main_letters = mb_str_split(mb_strtoupper($this->getMainSolution()->getSolution()));

        $assignment = $this->canAssignWordsToLetters($main_letters, $words);

        if (!$assignment->isValid()) {
            throw new Exception("A megadott szavakból nem állítható össze rejtvény!");
        }

        // Kiszámoljuk a legnagyobb balra eső eltolást, hogy a főmegoldás betűi középre kerüljenek a rejtvényben
        $maxLeftOffset = 0;
        foreach ($assignment->getHorizontalPositions() as $intersectionIndex) {
            if ($intersectionIndex > $maxLeftOffset) {
                $maxLeftOffset = $intersectionIndex;
            }
        }

        $mainWordXPos = $maxLeftOffset;
        $this->getMainSolution()->setXPos($mainWordXPos);
        $this->getMainSolution()->setYPos(0);

        $crosswordClues = [];
        foreach ($words as $i => $word) {
            $intersectionPos = $assignment->getHorizontalPositions()[$i];
            $yPos = $assignment->getVerticalPositions()[$i];

            // Legalább 0 kell legyen, abban az esetben, ha a főmegoldás egy betűjéhez van hozzárendelve a szó első betűje
            $startXPos = $mainWordXPos - $intersectionPos;

            $crosswordClue = new CrosswordClue(
                definition: $word->getDefinition(),
                solution: $word->getSolution(),
                direction: Direction::HORIZONTAL,
                x_pos: $startXPos,
                y_pos: $yPos,
                intersection_pos: $intersectionPos,
            );

            $crosswordClues[] = $crosswordClue;
        }

        // Y pozíció szerint növekvő sorba rakjuk a betűket, hogy könnyebb legyen őket majd elhelyezni a rácsban
        usort($crosswordClues, function ($a, $b) {
            return $a->getYPos() <=> $b->getYPos();
        });

        parent::setWords($crosswordClues);
    }

    /**
     * Megnézi, hogy a főmegoldás betűihez hozzárendelhetőek-e a megadott szavak
     * Azaz, minden főmegoldás betűhöz tartozik 1 megoldás, amik megfejtésével végül kijön majd a főmegoldás
     *
     * @param array $letters
     * @param array $words
     * @param int $letterIndex
     * @param array $usedWordIndexes
     * @param array $verticalPositions
     * @param array $horizontalPositions
     * 
     * @return AssignmentResult
     */
    private function canAssignWordsToLetters(
        array $letters,
        array $words,
        int $letterIndex = 0,
        array $usedWordIndexes = [],
        array $verticalPositions = [],
        array $horizontalPositions = [],
    ): AssignmentResult
    {
        // Ha végigértünk a betűkön, akkor sikerült mindhez szót találni
        if ($letterIndex >= count($letters)) {
            return new AssignmentResult(true, $verticalPositions, $horizontalPositions);
        }

        $currentLetter = $letters[$letterIndex];

        foreach ($words as $wordIndex => $word) {
            // Ha egyszer már felhasználtuk a szót, átugorjuk
            if (in_array($wordIndex, $usedWordIndexes, true)) {
                continue;
            }

            $solution = mb_strtoupper($word->getSolution());

            $pos = mb_strpos($solution, $currentLetter);

            // Ha megtaláljuk a keresett betűt a szóban, akkor eltároljuk az indexét mert felhasználtuk a szót
            // Majd újra meghívjuk a metódust
            if ($pos !== false) {
                $newUsed = [...$usedWordIndexes, $wordIndex];

                // Beállítjuk a szavak pozícióját a rejtvényen belül
                $newVertical = $verticalPositions;
                $newHorizontal = $horizontalPositions;

                $newVertical[$wordIndex] = $letterIndex;
                $newHorizontal[$wordIndex] = $pos;

                $result = $this->canAssignWordsToLetters($letters, $words, $letterIndex + 1, $newUsed, $newVertical, $newHorizontal);

                if ($result->isValid()) {
                    return $result;
                }
            }
        }

        return new AssignmentResult(false, [], []);
    }

    /**
     * Elkészíti a 2D tömböt, benne elrendezve a rejtvény definícióit
     *
     * @return array
     */
    public function generateGrid(): array
    {
        $words = $this->getWords();
        $mainWord = $this->getMainSolution();

        $height = $mainWord->getLength();
        $width = 0;

        // Kiszámoljuk a rács szélességét, hogy elférjenek benne a szavak
        foreach ($words as $word) {
            $wordEndX = $word->getXPos() + $word->getLength();
            if ($wordEndX > $width) {
                $width = $wordEndX;
            }
        }

        $this->setWidth($width);
        $this->setHeight($height);

        $grid = array_fill(0, $height, array_fill(0, $width, '#'));

        foreach ($words as $word) {
            foreach ($word->getCells() as $cell) {
                $grid[$cell['row']][$cell['col']] = $cell['letter'];
            }
        }

        foreach ($mainWord->getCells() as $cell) {
            $grid[$cell['row']][$cell['col']] = $cell['letter'];
        }

        return $grid;
    }

    /**
     * Előkészíti a szavakat az API válaszhoz, megadva a definíciójukat, megoldásukat, pozíciójukat, irányukat
     * Valamint a benne szereplő betűk pozícióját a rejtvényen belül, hogy megkönnyítse a rácsban való elhelyezésüket
     * 
     * @return array
     */
    public function getWordsForApi(): array
    {
        $wordsForApi = [];
        foreach ($this->getWords() as $word) {
            $wordsForApi[] = [
                'definition' => $word->getDefinition(),
                'solution' => $word->getSolution(),
                'x_pos' => $word->getXPos(),
                'y_pos' => $word->getYPos(),
                'direction' => $word->getDirection(),
                'cells' => $word->getCells(),
            ];
        } 
        return $wordsForApi;
    }
}
