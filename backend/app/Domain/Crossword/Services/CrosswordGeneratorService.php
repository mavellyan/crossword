<?php

namespace App\Domain\Crossword\Services;

use App\Domain\Crossword\Entities\Clue;
use App\Domain\Crossword\Entities\CrosswordClue;
use App\Domain\Crossword\Entities\SimpleCrossword;
use RuntimeException;

class CrosswordGeneratorService
{
    /**
     * Builds a demo simple crossword payload compatible with the current frontend.
     *
     * @param int $id
     * @return array<string, mixed>
     */
    public function build(int $id): array
    {
        $source = [
            'A Duna romaniai mellekfolyoja' => 'zsil',
            'Feljaro' => 'rampa',
            'Tisztessegtelen haszon' => 'sap',
            'Idos rokon' => 'dedi',
            'Fr. iro (Emile)' => 'zola',
        ];

        $words = [];
        foreach ($source as $definition => $solution) {
            $words[] = new Clue($definition, $solution);
        }

        $mainWord = new CrosswordClue('main_solution', 'piros', \App\Enums\Direction::HORIZONTAL, true);
        $crossword = new SimpleCrossword($mainWord);

        try {
            $crossword->setWords($words);
            $grid = $crossword->generateGrid();
        } catch (\Throwable $exception) {
            throw new RuntimeException('Nem sikerult betolteni a rejtvenyt.', 0, $exception);
        }

        $definitions = [];
        $solutions = [];

        foreach ($crossword->getWords() as $clue) {
            $definitions[] = $clue->getDefinition();
            $solutions[] = $clue->getSolution();
        }

        return [
            'id' => $id,
            'main_solution' => $mainWord->getSolution(),
            'grid' => $grid,
            'definitions' => $definitions,
            'solutions' => $solutions,
        ];
    }
}
