<?php

namespace App\Models;

readonly class AssignmentResult {
    /**
     * @param bool $valid
     * @param array<int, int> $vertical_positions
     * @param array<int, int> $horizontal_positions
     */
    public function __construct(
        public bool $valid,
        public array $vertical_positions,
        public array $horizontal_positions
    ) {}
}