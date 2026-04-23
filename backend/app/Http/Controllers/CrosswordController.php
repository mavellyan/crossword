<?php

// ============================================================
// PÉLDA HASZNÁLAT – Laravel Controller
// ============================================================

namespace App\Http\Controllers;

use App\Enums\Direction;
use App\Http\Resources\CrosswordResource;
use App\Services\ScandinavianCrosswordGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\CrosswordClue;
use App\Models\Clue;
use App\Models\SimpleCrossword;
use Exception;

class CrosswordController extends Controller
{
    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'main_solution' => 'required|string|min:3|max:20',
            'word_pairs' => 'required|array|min:3',
            'word_pairs.*.definition' => 'required|string',
            'word_pairs.*.solution' => 'required|string|min:2',
        ]);

        // A frontend [{definition: "...", solution: "..."}, ...] formátumban küldhet
        $pairs = [];
        foreach ($request->input('word_pairs') as $pair) {
            $pairs[$pair['definition']] = $pair['solution'];
        }

        try {
            $generator = new ScandinavianCrosswordGenerator();
            $puzzle = $generator->generate(
                $request->input('main_solution'),
                $pairs
            );

            return response()->json([
                'success' => true,
                'crossword' => $puzzle,
            ]);

        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function getCrossword($id): JsonResponse {
        $test_words = [];

        $testlist = [
            'A Duna romániai mellékfolyója' => 'zsil',
            'Feljáró' => 'rámpa',
            'Tisztességtelen haszon' => 'sáp',
            'Idős rokon' => 'dédi',
            'Fr. író (Emile)' => 'zola',
        ];

        foreach ($testlist as $key => $word) {
            $test_word = new Clue(
                $key,
                $word,
            );

            $test_words[] = $test_word;
        }

        $main_word = new CrosswordClue('main_solution', 'piros', Direction::VERTICAL, true);
        $crossword = new SimpleCrossword($main_word);
        $grid = null;

        try {
            $crossword->setWords($test_words);

            $grid = $crossword->generateGrid();
        } catch (Exception $e) {
            echo $e->getMessage();
        }

        if ($grid === null) {
            return response()->json([
                'success' => false,
                'message' => 'Nem sikerült betölteni a rejtvényt.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'crossword' => new CrosswordResource($crossword),
        ]);
    }
}


// ============================================================
// PÉLDA BEMENETI ADATOK ÉS VÁRHATÓ KIMENET
// ============================================================
//
// Főmegoldás: "BUDAPEST"
//
// Szópárok:
// [
//   "Édes ital"           => "BORSODA"       // B betű (index 0) → BUDAPEST[0] = B
//   "Magyar folyó"        => "DUNA"           // U betű → BUDAPEST[1] = U
//   "Téli sport"          => "BOBSLED"        // B betű → BUDAPEST[2] = D... stb.
//   ...
// ]
//
// A generátor megkeresi, hogy az egyes kulcsszavak melyik betűje adja
// a főmegoldás aktuális betűjét, majd úgy helyezi el vízszintesen,
// hogy az a betű pontosan a "megoldásoszlopba" essen.
//
// KIMENET STRUKTÚRA:
// {
//   "grid": [
//     [
//       {"type": "empty"},
//       {"type": "clue", "clue": "Magyar folyó", "direction": "H"},
//       {"type": "letter", "letter": "D"},
//       {"type": "solution", "letter": "U", "solution_index": 1},   ← KIEMELVE
//       {"type": "letter", "letter": "N"},
//       {"type": "letter", "letter": "A"},
//       ...
//     ],
//     ...
//   ],
//   "width": 22,
//   "height": 19,
//   "main_solution": "BUDAPEST",
//   "solution_col": 11,         ← ebben az oszlopban vannak a kiemelt cellák
//   "placed_words": [
//     {
//       "word": "DUNA",
//       "row": 2,
//       "col": 8,
//       "direction": "H",
//       "definition": "Magyar folyó",
//       "is_key_word": true,
//       "solution_offset": 3    ← a 4. betű (0-alapú: index 3) az "U"
//     },
//     ...
//   ]
// }


// ============================================================
// GYORS TESZT (php artisan tinker -ban futtatható)
// ============================================================

/*
$generator = new \App\Services\ScandinavianCrosswordGenerator();

$puzzle = $generator->generate(
    mainSolution: 'ALMA',
    wordPairs: [
        'Fővárosunk'              => 'BUDAPEST',
        'Kedvenc háziállat'       => 'MACSKA',
        'Téli sport'              => 'LESIKLÁS',
        'Édes gyümölcs'           => 'MANGÓ',
        'Magyar zenész'           => 'LISZT',
        'Naprendszerünk csillaga' => 'NAP',
        'Egyik évszak'            => 'TAVASZ',
        'Irodalmi műfaj'          => 'REGÉNY',
    ]
);

// Főmegoldás betűi: A, L, M, A
// Az algoritmus olyan szavakat keres, amelyek tartalmazzák ezeket:
//  A → BUDAPEST (3. betű), MACSKA (1. betű), MANGÓ (1. betű)...
//  L → LESIKLÁS (3. betű), LISZT (0. betű)...
//  M → MACSKA (0. betű), MANGÓ (0. betű)...
//  A → (már felhasználtakat nem veszi újra)

// A megoldásoszlopban felülről lefelé olvasva: A, L, M, A

echo "Rács mérete: {$puzzle['width']} x {$puzzle['height']}\n";
echo "Megoldásoszlop indexe: {$puzzle['solution_col']}\n";
echo "Elhelyezett szavak száma: " . count($puzzle['placed_words']) . "\n";
echo "Főmegoldás: {$puzzle['main_solution']}\n";

// Ellenőrzés: megoldás cellák sorba rendezve helyes betűket adnak-e
$solutionCells = [];
foreach ($puzzle['grid'] as $row) {
    foreach ($row as $cell) {
        if ($cell['type'] === 'solution') {
            $solutionCells[$cell['solution_index']] = $cell['letter'];
        }
    }
}
ksort($solutionCells);
$reconstructed = implode('', $solutionCells);
echo "Rekonstruált főmegoldás: $reconstructed\n"; // → ALMA
*/