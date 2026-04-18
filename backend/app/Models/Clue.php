<?php

namespace App\Models;

class Clue {
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
     * @param string $definition
     * @param string $solution
     * @param string|null $topic
     */
    public function __construct(
        string $definition,
        string $solution,
        ?string $topic = null,
    ) {
        $this->definition = $definition;
        $this->solution = $solution;
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
     * @return string
     */
    public function getTopic(): string {
        if ($this->topic === null) {
            return "általános";
        }

        return $this->topic;
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
     * @param string $topic
     * @return void
     */
    public function setTopic(string $topic): void {
        $this->topic = $topic;
    }

    /**
     * Debug segítség, minta:
     * Tanulóidőszak: inasév (általános, 6)
     *
     * @return string
     */
    public function getDebug(): string {
        return $this->definition . ": " .
            $this->solution .  " (" .
            $this->getTopic() . ", " .
            $this->length . ")";
    }
}
