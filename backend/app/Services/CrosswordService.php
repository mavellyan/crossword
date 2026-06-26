<?php

namespace App\Services;

use App\Models\Clue;
use App\Models\Crossword;
use App\Models\CrosswordClue;
use Illuminate\Support\Facades\DB;

class CrosswordService
{
    public function __construct(
        private readonly CrosswordGenerator $generator,
    ) {
    }

    public function getById(int $id): array
    {
        $crossword = Crossword::with([
            'crosswordClues.clue',
            'creator',
            'topics',
        ])->findOrFail($id);

        $gridData = $this->generator->generateGrid(
            $crossword->main_solution,
            $crossword->getWords(),
        );

        return [
            'crossword' => $crossword,
            'grid' => $gridData['grid'],
            'width' => $gridData['width'],
            'height' => $gridData['height'],
            'solution_col' => $gridData['solution_col'],
            'main_solution' => $gridData['main_solution'],
        ];
    }

    public function create(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $crossword = Crossword::create([
                'title' => $data['title'],
                'main_solution' => mb_strtolower($data['main_solution']),
                'creator_user_id' => $data['creator_user_id'] ?? null,
                'difficulty' => $data['difficulty'] ?? 'easy',
                'is_public' => $data['is_public'] ?? false,
            ]);

            $clues = collect($data['word_pairs'])
                ->map(function (array $pair) {
                    return Clue::firstOrCreate(
                        [
                            'definition' => $pair['definition'],
                            'solution' => mb_strtolower($pair['solution']),
                        ]
                    );
                })
                ->values();

            $placements = $this->generator->generatePlacements(
                $crossword->main_solution,
                $clues,
            );

            foreach ($placements as $placement) {
                $placement['crossword_id'] = $crossword->id;

                CrosswordClue::create($placement);
            }

            $crossword->load([
                'crosswordClues.clue',
                'creator',
                'topics',
            ]);

            $gridData = $this->generator->generateGrid(
                $crossword->main_solution,
                $crossword->getWords(),
            );

            return [
                'crossword' => $crossword,
                'grid' => $gridData['grid'],
                'width' => $gridData['width'],
                'height' => $gridData['height'],
                'solution_col' => $gridData['solution_col'],
                'main_solution' => $gridData['main_solution'],
            ];
        });
    }
}