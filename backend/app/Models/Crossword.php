<?php

namespace App\Models;

use Exception;

abstract class Crossword {
    /**
     * @var CrosswordClue
     */
    private CrosswordClue $main_solution;

    /**
     * @var CrosswordClue[]
     */
    private array $words;

    /**
     * @var bool
     */
    private bool $is_solved;

    /**
     * @param CrosswordClue $main_solution
     */
    public function __construct(CrosswordClue $main_solution) {
        $this->main_solution = $main_solution;
        $this->words = [];
        $this->is_solved = false;
    }

    /**
     * @return CrosswordClue
     */
    public function getMainSolution(): CrosswordClue {
        return $this->main_solution;
    }

    /**
     * @return CrosswordClue[]
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
     * @param CrosswordClue $main_solution
     * @return void
     */
    public function setMainSolution(CrosswordClue $main_solution): void {
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
