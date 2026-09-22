<?php

namespace Database\Factories;

use App\Models\Crossword;
use App\Models\User;
use App\Enums\Difficulty;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Crossword>
 */
class CrosswordFactory extends Factory
{
    protected $model = Crossword::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            // Egy default 5 betűs szó, hogy könnyű legyen rá építeni
            'main_solution' => 'TITOK', 
            // Létrehoz vagy hozzárendel egy usert, ha nem adunk meg explicit
            'user_id' => User::factory(), 
            'difficulty' => Difficulty::EASY,
            'is_public' => false,
        ];
    }
}