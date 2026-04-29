<?php

namespace App\Domain\Crossword\Entities;

use Exception;

abstract class Crossword {
    /**
     * @var string
     */
    private string $title;
    /**
     * @var string
     */
    private string $author;
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
     * @var int
     */
    private int $width;

    /**
     * @var int
     */
    private int $height;

    /**
     * @param CrosswordClue $main_solution
     */
    public function __construct(CrosswordClue $main_solution) {
        $this->title = "";
        $this->author = "";
        $this->main_solution = $main_solution;
        $this->words = [];
        $this->is_solved = false;
        $this->width = 0;
        $this->height = 0;
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

    /**
     * @param int $width
     * @return void
     */
    public function setWidth(int $width): void {
        $this->width = $width;
    }

    /**
     * @param int $height
     * @return void
     */
    public function setHeight(int $height): void {
        $this->height = $height;
    }

    /**
     * @return int
     */
    public function getWidth(): int {
        return $this->width;
    }

    /**
     * @return int
     */
    public function getHeight(): int {
        return $this->height;
    }

    /**
     * @return string
     */
    public function getTitle(): string {
        return $this->title;
    }

    /**
     * @param string $title
     * @return void
     */
    public function setTitle(string $title): void {
        $this->title = $title;
    }

    /**
     * @return string
     */
    public function getAuthor(): string {
        return $this->author;
    }

    public abstract function generateGrid();
}
