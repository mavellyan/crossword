<?php

namespace App\Models;

class CrosswordClue extends Clue {
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
    * @param ?string $topic
    */
    public function __construct(
        string $definition,
        string $solution,
        int $x_pos = -1,
        int $y_pos = -1,
        bool $is_main = false,
        ?string $topic = null,
    ) {
        parent::__construct($definition, $solution, $topic);
        $this->x_pos = $x_pos;
        $this->y_pos = $y_pos;
        $this->is_main = $is_main;
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
     * @return bool
     */
    public function isMain(): bool {
        return $this->is_main;
    }

    /**
     * @param bool $is_main
     * @return void
     */
    public function setMain(bool $is_main): void {
        $this->is_main = $is_main;
    }

    /**
     * Debug segítség, minta:
     * Tanulóidőszak: inasév (általános, 6) (x:1, y:1)
     *
     * @return string
     */
    #[\Override]
    public function getDebug(): string {
        return parent::getDebug() . " (x:" .
            $this->x_pos . ", y:" .
            $this->y_pos . ")";
    }
}