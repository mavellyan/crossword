<?php

namespace App\Enums;

enum Difficulty: string {
    case EASY = 'easy';
    case MEDIUM = 'medium';
    case HARD = 'hard';

    public function label(): string
    {
        return match($this) {
            self::EASY => 'Könnyű',
            self::MEDIUM => 'Közepes',
            self::HARD => 'Nehéz',
        };
    }
}