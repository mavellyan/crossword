<?php

namespace Database\Seeders;

use App\Models\Clue;
use App\Models\Topic;
use Illuminate\Database\Seeder;

class ClueTopicSeeder extends Seeder
{
    public function run(): void
    {
        $mappings = [
            'ALMA' => ['NÖVÉNYEK', 'GASZTRONÓMIA'],
            'KÖRTE' => ['NÖVÉNYEK', 'GASZTRONÓMIA'],
            'BARÁT' => ['IRODALOM', 'FILM'],
            'VÍZILÓ' => ['ÁLLATOK'],
            'ÁRNYÉK' => ['TUDOMÁNY', 'IRODALOM'],
            'ERDŐ' => ['NÖVÉNYEK', 'FÖLDRAJZ'],
            'NYÁR' => ['FÖLDRAJZ', 'TUDOMÁNY'],
            'ŐSZ' => ['FÖLDRAJZ', 'TUDOMÁNY'],
            'TALÁNY' => ['IRODALOM', 'MITOLÓGIA'],
            'TÜKÖR' => ['TUDOMÁNY', 'TECHNOLÓGIA'],
            'UTAZÁS' => ['FÖLDRAJZ', 'SPORT'],
            'ÖRÖM' => ['IRODALOM', 'ZENE'],
            'HÁZ' => ['TÖRTÉNELEM', 'TECHNOLÓGIA'],
            'EGÉR' => ['ÁLLATOK', 'TECHNOLÓGIA'],
            'SZÖVEG' => ['IRODALOM', 'TECHNOLÓGIA'],
            'BÁTOR' => ['MITOLÓGIA', 'TÖRTÉNELEM', 'FILM'],
            'TŰZ' => ['TUDOMÁNY', 'MITOLÓGIA'],
            'AJTÓ' => ['TECHNOLÓGIA', 'TÖRTÉNELEM'],
            'VILLÁM' => ['TUDOMÁNY', 'FÖLDRAJZ', 'MITOLÓGIA'],
            'IDŐ' => ['TUDOMÁNY', 'TÖRTÉNELEM'],
            'CSERESZNYE' => ['NÖVÉNYEK', 'GASZTRONÓMIA'],
            'SZOFTVER' => ['TECHNOLÓGIA'],
            'KEZDÉS' => ['SPORT', 'TÖRTÉNELEM'],
            'ÉRTÉK' => ['TUDOMÁNY', 'TÖRTÉNELEM'],
            'PÉLDA' => ['IRODALOM', 'TUDOMÁNY'],
            'HÍR' => ['TECHNOLÓGIA', 'TÖRTÉNELEM'],
            'SÉTA' => ['SPORT', 'FÖLDRAJZ'],
            'GÉP' => ['TECHNOLÓGIA'],
            'ORSÓ' => ['TECHNOLÓGIA', 'TÖRTÉNELEM', 'MITOLÓGIA'],
            'ÉLET' => ['TUDOMÁNY', 'MITOLÓGIA'],
        ];

        $topics = Topic::all()->keyBy('name');

        foreach ($mappings as $solution => $topicNames) {
            $clue = Clue::where('solution', $solution)->first();

            if (!$clue) {
                continue;
            }

            $topicIds = collect((array) $topicNames)
                ->map(fn ($name) => $topics->get($name)?->id)
                ->filter();

            if ($topicIds->isNotEmpty()) {
                $clue->topics()->syncWithoutDetaching($topicIds);
            }
        }
    }
}