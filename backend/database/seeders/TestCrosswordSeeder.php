<?php

namespace Database\Seeders;

use App\Enums\Direction;
use App\Models\Clue;
use App\Models\Crossword;
use App\Models\CrosswordClue;
use App\Models\User;
use App\Enums\Difficulty;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TestCrosswordSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {

            $crossword = Crossword::updateOrCreate(
                [
                    'title' => 'Teszt rejtvény - piros',
                ],
                [
                    'user_id' => null,
                    'main_solution' => 'piros',

                    'difficulty' => Difficulty::EASY,

                    'is_public' => true,
                ]
            );

            // Régi kapcsolatok törlése, hogy újrafuttatható legyen a seeder.
            $crossword->crosswordClues()->delete();

            /**
             * A főmegoldás: PIROS
             *
             * Elrendezés:
             *
             * row 0: #SÁP####
             * row 1: DÉDI####
             * row 2: ###RÁMPA
             * row 3: ##ZOLA##
             * row 4: ##ZSIL##
             *
             * A főmegoldás függőlegesen a 3. oszlopban van:
             * P, I, R, O, S
             */
            $mainClue = Clue::updateOrCreate(
                [
                    'definition' => 'main_solution',
                    'solution' => 'piros',
                ]
            );

            CrosswordClue::create([
                'crossword_id' => $crossword->id,
                'clue_id' => $mainClue->id,
                'direction' => Direction::VERTICAL,
                'start_row' => 0,
                'start_col' => 3,
            ]);

            $testClues = [
                [
                    'definition' => 'Tisztességtelen haszon',
                    'solution' => 'sáp',
                    'start_row' => 0,
                    'start_col' => 1,
                ],
                [
                    'definition' => 'Idős rokon',
                    'solution' => 'dédi',
                    'start_row' => 1,
                    'start_col' => 0,
                ],
                [
                    'definition' => 'Feljáró',
                    'solution' => 'rámpa',
                    'start_row' => 2,
                    'start_col' => 3,
                ],
                [
                    'definition' => 'Fr. író (Emile)',
                    'solution' => 'zola',
                    'start_row' => 3,
                    'start_col' => 2,
                ],
                [
                    'definition' => 'A Duna romániai mellékfolyója',
                    'solution' => 'zsil',
                    'start_row' => 4,
                    'start_col' => 2,
                ],
            ];

            foreach ($testClues as $testClue) {
                $clue = Clue::updateOrCreate(
                    [
                        'definition' => $testClue['definition'],
                        'solution' => $testClue['solution'],
                    ]
                );

                CrosswordClue::create([
                    'crossword_id' => $crossword->id,
                    'clue_id' => $clue->id,
                    'direction' => Direction::HORIZONTAL,
                    'start_row' => $testClue['start_row'],
                    'start_col' => $testClue['start_col'],
                ]);
            }
        });
    }
}