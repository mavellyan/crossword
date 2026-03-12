<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CrosswordGenerator;

class TestGenerator extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:generator';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    public static string $main_solution = 'üzemiétkezde';

    public static array $words = [
        'indulatos, ingerült' => 'dühös',
        'Fr. író (Emile)' => 'zola',
        'Perben áll!' => 'er',
        'A vanádium vegyjele' => 'v',
        'Szerelemisten' => 'ámor',
        'Járomba fogott (állat)' => 'igás',
        'Révben van!' => 'é',
        'Komárom része!' => 'má',
        'Ötlet' => 'tipp',
        'Robbanóeszköz' => 'akna',
        'Előadó, röviden' => 'ea',
        'Amper, röviden' => 'a',
        'A Duna romániai mellékfolyója' => 'zsil',
        'Idős rokon' => 'dédi',
        'Vízi sportot űz' => 'evez',
        'Római 500-as' => 'd',
        'Pecázó eszköze' => 'horog',
        'Régi hosszmérték' => 'öl',
        'Maró hatású vegyület' => 'sav',
        'Ugyan!' => 'á',
        'Feljáró' => 'rámpa',
        'Tisztességtelen haszon' => 'sáp',
        'Tanulóidőszak' => 'inasév',
        'Ritka női név' => 'aliz',
        'Hozzám' => 'ide',
        'Osztrák autók jelzése' => 'a',
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // CrosswordGenerator::generate();

        $generator = new \App\Services\ScandinavianCrosswordGenerator();

        
        $puzzle = $generator->generate(
            'ALMA',
            [
                'Fővárosunk' => 'BUDAPEST',
                'Kedvenc háziállat' => 'MACSKA',
                'Téli sport' => 'LESIKLÁS',
                'Édes gyümölcs' => 'MANGÓ',
                'Magyar zenész' => 'LISZT',
                'Naprendszerünk csillaga' => 'NAP',
                'Egyik évszak' => 'TAVASZ',
                'Irodalmi műfaj' => 'REGÉNY',
            ]
        );

        // $puzzle = $generator->generate(self::$main_solution, self::$words);

        echo "Rács mérete: {$puzzle['width']} x {$puzzle['height']}\n";
        echo "Megoldásoszlop indexe: {$puzzle['solution_col']}\n";
        echo "Elhelyezett szavak száma: " . count($puzzle['placed_words']) . "\n";
        // echo "Puzzle: " . json_encode($puzzle, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        foreach ($puzzle['grid'] as $row) {
            foreach ($row as $cell) {
                if ($cell['type'] === 'empty') {
                    echo ' ';
                } elseif ($cell['type'] === 'letter' || $cell['type'] === 'solution') {
                    echo $cell['letter'];
                } else {
                    echo '[]';
                }
            }
            echo "\n";
        }
    }
}
