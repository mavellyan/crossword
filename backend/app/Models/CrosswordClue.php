<?php

namespace App\Models;

use App\Enums\Direction;

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
     * A megoldásban való metszéspozíciója, -1 esetén nincs metszés
     * 
     * @var int
     */
    private int $intersection_pos;

    /**
     * Főmegoldás-e az adott szó
     *
     * @var bool
     */
    private bool $is_main;

    /**
     * A megoldás iránya, "horizontal" vagy "vertical"
     * 
     * @var Direction
     */
    private Direction $direction;

    /**
    * @param string $definition
    * @param string $solution
    * @param Direction $direction
    * @param int $x_pos
    * @param int $y_pos
    * @param int $intersection_pos
    * @param bool $is_main
    * @param ?string $topic
    */
    public function __construct(
        string $definition,
        string $solution,
        Direction $direction,
        int $x_pos = -1,
        int $y_pos = -1,
        int $intersection_pos = -1,
        bool $is_main = false,
        ?string $topic = null,
    ) {
        parent::__construct($definition, $solution, $topic);
        $this->x_pos = $x_pos;
        $this->y_pos = $y_pos;
        $this->intersection_pos = $intersection_pos;
        $this->is_main = $is_main;
        $this->direction = $direction;
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
    * @return int
    */
    public function getIntersectionPos(): int {
        return $this->intersection_pos;
    }

    /**
     * @param int $intersection_pos
     * @return void
     */
    public function setIntersectionPos(int $intersection_pos): void {
        $this->intersection_pos = $intersection_pos;
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
     * @return string
     */
    public function getDirection(): string {
        return $this->direction->value;
    }

    /**
     * @param Direction $direction
     * @return void
     */
    public function setDirection(Direction $direction): void {
        $this->direction = $direction;
    }

    /**
     * Debug segítség, minta:
     * Tanulóidőszak: inasév (általános, 6) (direction: horizontal, x:1, y:1)
     *
     * @return string
     */
    #[\Override]
    public function getDebug(): string {
        return parent::getDebug() . " (direction:" .
            $this->direction->value . ", x:" .
            $this->x_pos . ", y:" .
            $this->y_pos . ")";
    }

    /**
     * Visszaadja a megoldás betűit és pozícióit egy tömbben, hogy megkönnyítse a rácsban való elhelyezésüket
     * 
     * @return array
     */
    public function getCells(): array
    {
        $cells = [];
        $solution = mb_strtoupper($this->getSolution());
        for ($i = 0; $i < mb_strlen($solution); $i++) {
            $cells[] = [
                'letter' => mb_substr($solution, $i, 1),
                'row' => $this->y_pos + ($this->direction->value === 'vertical' ? $i : 0),
                'col' => $this->x_pos + ($this->direction->value === 'horizontal' ? $i : 0),
            ];
        }
        return $cells;
    }
}