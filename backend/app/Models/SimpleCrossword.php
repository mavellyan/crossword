<?php

namespace App\Models;

use App\Models\Crossword;

class SimpleCrossword extends Crossword {
    #[\Override]
    public function setWords(array $words): void {
        if (empty($words)) {
            throw new Exception("Üres tömb átadva?");
        }

        if (count($words) > strlen($this->main_solution->getSolution())) {
            throw new Exception("Nem egyezik a szavak száma és a főmegoldás hossza!");
        }

        $letters = str_split($this->main_solution->getSolution());


    }

    public function generateGrid(): void
    {
        foreach ($this->getWords() as $word) {
            echo $word->getDebug() . "\n";
        }
    }
}
