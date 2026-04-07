<?php

namespace App\Models;

class AssignmentResult {
    private bool $valid;
    private array $vertical_positions;
    private array $horizontal_positions;

    public function __construct(bool $valid, array $vertical_positions, array $horizontal_positions) {
        $this->valid = $valid;
        $this->vertical_positions = $vertical_positions;
        $this->horizontal_positions = $horizontal_positions;
    }

    public function isValid(): bool {
        return $this->valid;
    }

    public function getVerticalPositions(): array {
        return $this->vertical_positions;
    }

    public function getHorizontalPositions(): array {
        return $this->horizontal_positions;
    }
}