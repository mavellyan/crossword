<?php

namespace App\Services;

use App\Models\Clue;
use App\Models\Crossword;
use App\Models\CrosswordClue;
use App\Models\CrosswordAttempt;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;

class CrosswordService
{
    public function __construct(
        private readonly CrosswordGenerator $generator,
    ) {
    }

    public function getById(int $id, ?int $userId = null): array
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

    public function createAutomatically(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $crossword = Crossword::create([
                'title' => $data['title'],
                'main_solution' => mb_strtoupper($data['main_solution']),
                'user_id' => $data['user_id'] ?? null,
                'difficulty' => $data['difficulty'] ?? 'easy',
                'is_public' => $data['is_public'] ?? false,
            ]);

            $clues = collect($data['word_pairs'])
                ->map(function (array $pair) {
                    return Clue::firstOrCreate(
                        [
                            'definition' => $pair['definition'],
                            'solution' => mb_strtoupper($pair['solution']),
                        ]
                    );
                })
                ->values();

            $placements = $this->generator->generatePlacementsAutomatically(
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

    public function createFromClueIds(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $mainSolution = mb_strtoupper($data['main_solution']);
            $clueIds = array_values($data['clue_ids']);

            if (count($clueIds) !== mb_strlen($mainSolution)) {
                throw new Exception('Pontosan annyi szót kell választani, ahány betűből áll a főmegoldás.');
            }

            if (count($clueIds) !== count(array_unique($clueIds))) {
                throw new Exception('Ugyanazt a szót nem lehet többször kiválasztani.');
            }

            $cluesById = Clue::whereIn('id', $clueIds)
                ->get()
                ->keyBy('id');

            $clues = collect($clueIds)
                ->map(function (int $id) use ($cluesById) {
                    $clue = $cluesById->get($id);

                    if (!$clue) {
                        throw new Exception('A kiválasztott szavak között van nem létező szó.');
                    }

                    return $clue;
                })
                ->values();

            $crossword = Crossword::create([
                'title' => $data['title'],
                'main_solution' => $mainSolution,
                'user_id' => $data['user_id'] ?? null,
                'difficulty' => $data['difficulty'] ?? 'easy',
                'is_public' => $data['is_public'] ?? false,
            ]);

            if (!empty($data['topic_ids'])) {
                $crossword->topics()->attach($data['topic_ids']);
            }

            $placements = $this->generator->generatePlacementsFixedOrder(
                $crossword->main_solution,
                $clues,
            );

            foreach ($placements as $placement) {
                CrosswordClue::create([
                    'crossword_id' => $crossword->id,
                    'clue_id' => $placement['clue_id'],
                    'direction' => $placement['direction'],
                    'start_row' => $placement['start_row'],
                    'start_col' => $placement['start_col'],
                    'intersection_index' => $placement['intersection_index'],
                    'is_main' => false,
                ]);
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

    public function getAllForList(array $filters = [])
    {
        $query = Crossword::query()
            ->with('creator')
            ->withCount([
                'crosswordClues as words_count' => function ($query) {
                    $query->where('is_main', false);
                },
            ]);
            //->where('is_public', true); - egyelőre kikapcsolva, mivel még tesztjelleg az egész

        if (!empty($filters['search'])) {
            $search = $filters['search'];

            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%' . $search . '%');
            });
        }

        if (!empty($filters['topics'])) {
            $query->whereHas('topics', function ($q) use ($filters) {
                $q->whereIn('topics.id', $filters['topics']);
            });
        }

        if (!empty($filters['difficulties'])) {
            $query->whereIn('difficulty', $filters['difficulties']);
        }

        if (!empty($filters['creators'])) {
            $query->whereIn('user_id', $filters['creators']);
        }

        if (!empty($filters['status']) && $filters['status'] !== 'status_all') {
            $userId = $filters['userId'] ?? null;

            if (!$userId) {
                throw new Exception('A státusz szűrő csak bejelentkezett felhasználók számára elérhető.');
            }

            switch ($filters['status']) {
                case 'status_new':
                    $query->whereDoesntHave('attempts', function ($q) use ($userId) {
                        $q->where('user_id', $userId);
                    });
                    break;
                case 'status_in_progress':
                    $query->whereHas('attempts', function ($q) use ($userId) {
                        $q->where('user_id', $userId)->where('status', 'in_progress');
                    });
                    break;
                case 'status_completed':
                    $query->whereHas('attempts', function ($q) use ($userId) {
                        $q->where('user_id', $userId)->where('status', 'completed');
                    });
                    break;
                default:
                    throw new Exception('Ismeretlen státusz szűrő: ' . $filters['status']); 
            }
        }

        if (!empty($filters['sortOrder'])) {
            switch ($filters['sortOrder']) {
                case 'dateAsc':
                    $query->orderBy('created_at', 'asc');
                    break;
                case 'dateDesc':
                    $query->orderBy('created_at', 'desc');
                    break;
                case 'titleAsc':
                    $query->orderBy('title', 'asc');
                    break;
                case 'titleDesc':
                    $query->orderBy('title', 'desc');
                    break;
                default:
                    // Alapértelmezett rendezés: legújabb előre
                    $query->orderBy('created_at', 'desc');
                    break;
            }
        } else {
            // Alapértelmezett rendezés: legújabb előre
            $query->orderBy('created_at', 'desc');
        }

        return $query->get();
    }
}