<?php

namespace Database\Seeders;

use App\Models\Clue;
use Illuminate\Database\Seeder;

class TestWordsSeeder extends Seeder
{
    public function run(): void
    {
        $words = [
            ['solution' => 'ALMA', 'definition' => 'Piros vagy zold gyümölcs.'],
            ['solution' => 'KÖRTE', 'definition' => 'Ősszel érik, almához hasonló gyümölcs.'],
            ['solution' => 'BARÁT', 'definition' => 'Közeli ismerős, akiben megbízol.'],
            ['solution' => 'VÍZILÓ', 'definition' => 'Nagytestű afrikai emlős.'],
            ['solution' => 'ÁRNYÉK', 'definition' => 'Fénytakarás után keletkező sötét forma.'],
            ['solution' => 'ERDŐ', 'definition' => 'Fákkal sűrűn benőtt terület.'],
            ['solution' => 'NYÁR', 'definition' => 'Az év legmelegebb évszaka.'],
            ['solution' => 'ŐSZ', 'definition' => 'Lombhullás időszaka.'],
            ['solution' => 'TALÁNY', 'definition' => 'Nehezen megfejthető kérdés vagy rejtély.'],
            ['solution' => 'TÜKÖR', 'definition' => 'Sima felület, amely visszaveri a képet.'],
            ['solution' => 'UTAZÁS', 'definition' => 'Helyváltoztatás hosszabb távon.'],
            ['solution' => 'ÖRÖM', 'definition' => 'Pozitív érzelmi állapot.'],
            ['solution' => 'HÁZ', 'definition' => 'Lakóépület.'],
            ['solution' => 'EGÉR', 'definition' => 'Kis testű rágcsáló.'],
            ['solution' => 'SZÖVEG', 'definition' => 'Leírt mondatok összessége.'],
            ['solution' => 'BÁTOR', 'definition' => 'Nem fél a nehéz helyzetektől.'],
            ['solution' => 'TŰZ', 'definition' => 'Égéssel járó jelenség.'],
            ['solution' => 'AJTÓ', 'definition' => 'Bejáratot záró nyíló.'],
            ['solution' => 'VILLÁM', 'definition' => 'Zivatarban látható fényjelenség.'],
            ['solution' => 'IDŐ', 'definition' => 'Múlás, tartam vagy időpont fogalma.'],
            ['solution' => 'CSERESZNYE', 'definition' => 'A nyár elején érő, apró piros gyümölcs.'],
            ['solution' => 'SZOFTVER', 'definition' => 'Számítógépen futtatható programrendszer.'],
            ['solution' => 'KEZDÉS', 'definition' => 'Valaminek az elindítása.'],
            ['solution' => 'ÉRTÉK', 'definition' => 'Valaminek a fontossága vagy ára.'],
            ['solution' => 'PÉLDA', 'definition' => 'Valami szemléltetésére szolgáló minta.'],
            ['solution' => 'HÍR', 'definition' => 'Friss információ egy eseményről.'],
            ['solution' => 'SÉTA', 'definition' => 'Lassú tempójú gyaloglás.'],
            ['solution' => 'GÉP', 'definition' => 'Mechanikus vagy elektronikus eszköz.'],
            ['solution' => 'ORSÓ', 'definition' => 'Fonállal használt kis hengeres eszköz.'],
            ['solution' => 'ÉLET', 'definition' => 'A létezés biológiai folyamata.'],
        ];

        foreach ($words as $word) {
            Clue::updateOrCreate(
                [
                    'solution' => mb_strtoupper($word['solution']),
                    'definition' => $word['definition'],
                ],
                [
                    'solution' => mb_strtoupper($word['solution']),
                    'definition' => $word['definition'],
                ]
            );
        }
    }
}