<?php

namespace Database\Seeders;

use App\Models\Topic;
use Illuminate\Database\Seeder;

class TopicSeeder extends Seeder
{
    public function run(): void
    {
        $topics = [
            'ÁLLATOK',
            'NÖVÉNYEK',
            'FÖLDRAJZ',
            'TÖRTÉNELEM',
            'IRODALOM',
            'ZENE',
            'FILM',
            'SPORT',
            'TUDOMÁNY',
            'GASZTRONÓMIA',
            'MITOLÓGIA',
            'TECHNOLÓGIA',
        ];

        foreach ($topics as $topic) {
            Topic::firstOrCreate([
                'name' => $topic,
            ]);
        }
    }
}