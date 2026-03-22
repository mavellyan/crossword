<?php

namespace App\Models;

use App\Models\Crossword;
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
    public function setWords(array $words): void {
        if (empty($words)) {
            throw new Exception("Üres tömb átadva?");
        }

        if (count($words) > $this->getMainSolution()->getLength()) {
            throw new Exception("Nem egyezik a szavak száma és a főmegoldás hossza!");
        }

        foreach ($words as $word) {
            if (!$word instanceof Word) {
                throw new Exception("A tömbbe bekerült valami, ami nem Word típusú?");
            }
        }

        $main_letters = mb_str_split(mb_strtoupper($this->getMainSolution()->getSolution()));

        if (!$this->canAssignWordsToLetters($main_letters, $words)) {
            throw new Exception("A megadott szavakból nem állítható össze rejtvény!");
        }

        parent::setWords($words);
    }

    /**
     * Megnézi, hogy a főmegoldás betűihez hozzárendelhetőek-e a megadott szavak
     * Azaz, minden főmegoldás betűhöz tartozik 1 megoldás, amik megfejtésével végül kijön majd a főmegoldás
     *
     * @param array $letters
     * @param array $words
     * @param int $letterIndex
     * @param array $usedWordIndexes
     * @return bool
     */
    private function canAssignWordsToLetters(
        array $letters,
        array $words,
        int $letterIndex = 0,
        array $usedWordIndexes = []
    ): bool
    {
        // Ha végigértünk a betűkön, akkor sikerült mindhez szót találni
        if ($letterIndex >= count($letters)) {
            return true;
        }

        $currentLetter = $letters[$letterIndex];

        foreach ($words as $wordIndex => $word) {
            // Ha egyszer már felhasználtuk a szót, átugorjuk
            if (in_array($wordIndex, $usedWordIndexes, true)) {
                continue;
            }

            $solution = mb_strtoupper($word->getSolution());

            // Ha megtaláljuk a keresett betűt a szóban, akkor eltároljuk az indexét mert felhasználtuk a szót
            // Majd újra meghívjuk a metódust
            if (mb_strpos($solution, $currentLetter) !== false) {
                $newUsed = $usedWordIndexes;
                $newUsed[] = $wordIndex;

                // Beállítjuk a szavak pozícióját a rejtvényen belül
                $word->setYPos($letterIndex);
                $word->setXPos(mb_strpos($solution, $currentLetter));

                if ($this->canAssignWordsToLetters($letters, $words, $letterIndex + 1, $newUsed)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Elkészíti a 2D tömböt, benne elrendezve a rejtvény definícióit
     *
     * @return array
     */
    public function generateGrid(): array
    {
        $x_positions = [];
        $y_positions = [];
        foreach ($this->getWords() as $word) {
            $x_positions[] = $word->getXPos();
            $y_positions[] = $word->getYPos();
        }

        $width = max($x_positions);
        $height = max($y_positions);

        $grid = array_fill(0, $width, array_fill(0, $height, '.'));

        foreach ($this->getWords() as $word) {
            $letters = mb_str_split(mb_strtoupper($word->getSolution()));
            $index = 0;

            foreach ($letters as $letter) {
                $grid[$word->getYPos()][$index] = $letter;
                $index++;
            }
        }

        return $grid;
    }
}
