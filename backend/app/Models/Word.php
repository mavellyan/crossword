<?php

namespace App\Models;

class Word {
    private int $length;
    private string $topic;
    private string $definition;
    private string $solution;
    private int $x_pos;
    private int $y_pos;
    private bool $is_main;

    public function __construct(
        int $length,
        string $topic,
        string $definition,
        string $solution,
        int $x_pos = -1,
        int $y_pos = -1,
        bool $is_main = false,
    ) {
        $this->length = $length;
        $this->topic = $topic;
        $this->definition = $definition;
        $this->solution = $solution;
        $this->x_pos = $x_pos;
        $this->y_pos = $y_pos;
        $this->is_main = $is_main;
    }

    public function getLength(): int {
        return $this->length;
    }

    public function getXPos(): int {
        return $this->x_pos;
    }

    public function getYPos(): int {
        return $this->y_pos;
    }

    public function getTopic(): string {
        return $this->topic;
    }

    public function isMain(): bool {
        return $this->is_main;
    }

    public function getDefinition(): string {
        return $this->definition;
    }

    public function getSolution(): string {
        return $this->solution;
    }

    public function setXPos(int $x_pos): void {
        $this->x_pos = $x_pos;
    }

    public function setYPos(int $y_pos): void {
        $this->y_pos = $y_pos;
    }

    public function setMain(bool $is_main): void {
        $this->is_main = $is_main;
    }

    public function getDebug(): string {
        return $this->definition . ": " .
            $this->solution .  " (" .
            $this->topic . ", " .
            $this->length . ", x:" .
            $this->x_pos . ", y:" .
            $this->y_pos . ")";
    }
}
