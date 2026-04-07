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

        if (!$this->canAssignWordsToLetters($main_letters, $words)) {
            throw new Exception("A megadott szavakból nem állítható össze rejtvény!");
        }

        // Y pozíció szerint növekvő sorba rakjuk a betűket, hogy könnyebb legyen őket majd elhelyezni a rácsban
        usort($words, function ($a, $b) {
            return $a->getYPos() <=> $b->getYPos();
        });

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
        $height = $this->getMainSolution()->getLength();
        $width = $this->getMinCrosswordWidth();

        $grid = [];

        $mainWordPos = $width / 2;


        foreach ($this->getWords() as $word) {
            $grid[] = $this->generateRow($word, $mainWordPos, $width);
        }


        return $grid;
    }

    /**
     * Visszadja a rejtvény minimum szélességét (a legszélesebb szó kétszerese)
     * Ha páros, akkor hozzáadunk egyet, hogy a közepére tehessük a főmegoldást
     *
     * @return int
     */
    public function getMinCrosswordWidth(): int
    {
        $width = 0;
        foreach ($this->getWords() as $word) {
            if ($word->getLength() * 2 > $width) {
                $width = $word->getLength() * 2;
            }
        }

        if ($width % 2 === 0) {
            $width++;
        }

        return $width;
    }

    /**
     * Feltölti a sorokat az adott szó betűivel
     *
     * @param Clue $word
     * @param int $mainWordPos
     * @param int $width
     * @return array
     */
    public function generateRow(Clue $word, int $mainWordPos, int $width): array
    {
        $row = array_fill(0, $width, '#');
        $letters = mb_str_split(mb_strtoupper($word->getSolution()));
        $matchingPos = $word->getXPos();

        // Különválasztjuk a metszet előtti betűket és a metszet utáni betűket,
        // Hogy könnyebben megtaláljuk a pozíciójukat az adott sorban
        $lettersBeforeIntersection = array_slice($letters, 0, $matchingPos);
        $lettersAfterIntersection = array_slice($letters, $matchingPos);

        // A sor elejétől elindulunk a közepéig
        for ($i = 0; $i < $mainWordPos; $i++) {
            // Ha az adott pozíciónktól a metszetig tartó betűk pont elérnék a főmegoldás pozícióit
            // Akkor feltöltjük a főmegoldásig a szó előtte lévő betűivel
            if ($i + count($lettersBeforeIntersection) === $mainWordPos) {
                foreach ($lettersBeforeIntersection as $letter) {
                    $row[$i] = $letter;
                    $i++;
                }
                break;
            }
        }

        // Utána csak végigmegyünk a szó maradék betűin a főmegoldás pozíciójától és beletesszük a sorba őket
        foreach ($lettersAfterIntersection as $letterIndex => $letter) {
            $row[$mainWordPos + $letterIndex] = $letter;
        }

        return $row;
    }
}
