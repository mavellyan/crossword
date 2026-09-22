<?php

namespace Database\Factories;

use App\Models\Clue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Clue>
 */
class ClueFactory extends Factory
{
    protected $model = Clue::class;

    public function definition(): array
    {
        return [
            // Generálunk egy random mondatot definíciónak
            'definition' => fake()->sentence(4), 
            // Generálunk egy random szót, és nagybetűssé tesszük
            'solution' => mb_strtoupper(fake()->word()), 
        ];
    }
}