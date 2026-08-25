<?php

namespace Database\Seeders;

use App\Models\Topic;
use Illuminate\Database\Seeder;

class TopicSeeder extends Seeder
{
    public function run(): void
    {
        $topics = [
            [
                'name' => 'ÁLLATOK',
                'description' => 'Háziállatok, vadon élő állatok, madarak, halak és egyéb állatfajok.',
            ],
            [
                'name' => 'NÖVÉNYEK',
                'description' => 'Fák, virágok, gyógynövények, zöldségek és egyéb növények.',
            ],
            [
                'name' => 'FÖLDRAJZ',
                'description' => 'Országok, városok, folyók, hegyek, tengerek és egyéb földrajzi témák.',
            ],
            [
                'name' => 'TÖRTÉNELEM',
                'description' => 'Történelmi események, személyek, korszakok és évszámok.',
            ],
            [
                'name' => 'IRODALOM',
                'description' => 'Írók, költők, művek, szereplők és irodalmi fogalmak.',
            ],
            [
                'name' => 'ZENE',
                'description' => 'Zenészek, együttesek, hangszerek, műfajok és zenetörténeti témák.',
            ],
            [
                'name' => 'FILM',
                'description' => 'Filmek, színészek, rendezők, szereplők és filmes fogalmak.',
            ],
            [
                'name' => 'SPORT',
                'description' => 'Sportágak, sportolók, csapatok, versenyek és sporttal kapcsolatos fogalmak.',
            ],
            [
                'name' => 'TUDOMÁNY',
                'description' => 'Fizika, kémia, biológia, csillagászat és egyéb tudományterületek.',
            ],
            [
                'name' => 'GASZTRONÓMIA',
                'description' => 'Ételek, italok, alapanyagok, főzési technikák és gasztronómiai fogalmak.',
            ],
            [
                'name' => 'MITOLÓGIA',
                'description' => 'Görög, római, skandináv és egyéb mitológiák istenei, hősei és történetei.',
            ],
            [
                'name' => 'TECHNOLÓGIA',
                'description' => 'Számítástechnika, internet, elektronika és modern technológiai fogalmak.',
            ],
        ];

        foreach ($topics as $topic) {
            Topic::updateOrCreate(
                ['name' => $topic['name']],
                ['description' => $topic['description']]
            );
        }
    }
}