<?php

namespace App\Models;

use Exception;

abstract class Crossword {
    /**
     * @var Clue
     */
    private Clue $main_solution;

    /**
     * @var Clue[]
     */
    private array $words;

    /**
     * @var bool
     */
    private bool $is_solved;

    /**
     * @param Clue $main_solution
     */
    public function __construct(Clue $main_solution) {
        $this->main_solution = $main_solution;
        $this->words = [];
        $this->is_solved = false;
    }

    /**
     * @return Clue
     */
    public function getMainSolution(): Clue {
        return $this->main_solution;
    }

    /**
     * @return Clue[]
     */
    public function getWords(): array {
        return $this->words;
    }

    /**
     * @return bool
     */
    public function isSolved(): bool {
        return $this->is_solved;
    }

    /**
     * @param Clue $main_solution
     * @return void
     */
    public function setMainSolution(Clue $main_solution): void {
        $this->main_solution = $main_solution;
    }

    /**
     * @param array $words
     * @return void
     * @throws Exception
     */
    public function setWords(array $words): void {
        $this->words = $words;
    }

    /**
     * @param bool $is_solved
     * @return void
     */
    public function setIsSolved(bool $is_solved): void {
        $this->is_solved = $is_solved;
    }

    public abstract function generateGrid();
}
