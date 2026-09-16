<?php

namespace App\Domain\Crossword;

final readonly class PlacementValidationResult
{
    public function __construct(
        public bool $valid,
        public array $errors,
        public int $intersectionCount,
    ) {
    }
}