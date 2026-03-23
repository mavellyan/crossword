<?php

namespace App\Models;

class Word {
    /**
     * A megoldás hossza
     *
     * @var int
     */
    private int $length;

    /**
     * A megoldás témája (ha null, akkor általános)
     *
     * @var ?string
     */
    private ?string $topic;

    /**
     * Az adott megoldás a rejtvényben
     *
     * @var string
     */
    private string $definition;

    /**
     * Az adott megoldáshoz tartozó kérdés/definíció
     * Főmegoldások esetén main_solution az értéke
     *
     * @var string
     */
    private string $solution;

    /**
     * A megoldás vízszintes pozíciója, -1 esetén még nincs elhelyezve
     *
     * @var int
     */
    private int $x_pos;

    /**
     * A megoldás függőleges pozíciója, -1 esetén még nincs elhelyezve
     *
     * @var int
     */
    private int $y_pos;

    /**
     * Főmegoldás-e az adott szó
     *
     * @var bool
     */
    private bool $is_main;

    /**
     * @param string $definition
     * @param string $solution
     * @param int $x_pos
     * @param int $y_pos
     * @param bool $is_main
     * @param string|null $topic
     */
    public function __construct(
        string $definition,
        string $solution,
        int $x_pos = -1,
        int $y_pos = -1,
        bool $is_main = false,
        string $topic = null,
    ) {
        $this->definition = $definition;
        $this->solution = $solution;
        $this->x_pos = $x_pos;
        $this->y_pos = $y_pos;
        $this->is_main = $is_main;
        $this->topic = $topic;
        $this->length = mb_strlen($solution);
    }

    /**
     * @return int
     */
    public function getLength(): int {
        return $this->length;
    }

    /**
     * @return int
     */
    public function getXPos(): int {
        return $this->x_pos;
    }

    /**
     * @return int
     */
    public function getYPos(): int {
        return $this->y_pos;
    }

    /**
     * @return string
     */
    public function getTopic(): string {
        if ($this->topic === null) {
            return "általános";
        }

        return $this->topic;
    }

    /**
     * @return bool
     */
    public function isMain(): bool {
        return $this->is_main;
    }

    /**
     * @return string
     */
    public function getDefinition(): string {
        return $this->definition;
    }

    /**
     * @return string
     */
    public function getSolution(): string {
        return $this->solution;
    }

    /**
     * @param int $x_pos
     * @return void
     */
    public function setXPos(int $x_pos): void {
        $this->x_pos = $x_pos;
    }

    /**
     * @param int $y_pos
     * @return void
     */
    public function setYPos(int $y_pos): void {
        $this->y_pos = $y_pos;
    }

    /**
     * @param bool $is_main
     * @return void
     */
    public function setMain(bool $is_main): void {
        $this->is_main = $is_main;
    }

    /**
     * @param string $topic
     * @return void
     */
    public function setTopic(string $topic): void {
        $this->topic = $topic;
    }

    /**
     * Debug segítség, minta:
     * Tanulóidőszak: inasév (általános, 6, x:1, y:1)
     *
     * @return string
     */
    public function getDebug(): string {
        return $this->definition . ": " .
            $this->solution .  " (" .
            $this->getTopic() . ", " .
            $this->length . ", x:" .
            $this->x_pos . ", y:" .
            $this->y_pos . ")";
    }
}
