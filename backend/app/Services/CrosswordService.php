<?php

namespace App\Services;

use App\Models\Clue;
use App\Models\Crossword;
use App\Models\CrosswordClue;
use Illuminate\Support\Facades\DB;
use Exception;
use App\Enums\Direction;
use App\Domain\Crossword\Placement;
use App\Services\PlacementValidator;
use App\Exceptions\InvalidCrosswordLayout;
use App\Enums\Difficulty;
use Illuminate\Auth\Access\AuthorizationException;

class CrosswordService
{
    public function __construct(
        private readonly CrosswordGenerator $generator,
        private readonly PlacementValidator $placementValidator,
    ) {
    }

    public function getById(int $id, ?int $userId = null): array
    {
        $crossword = Crossword::with([
            'crosswordClues.clue',
            'creator',
            'topics',
        ])->findOrFail($id);

        if ($crossword->user_id !== $userId && !$crossword->is_public) {
            throw new AuthorizationException('Nincs jogosultságod a rejtvény megtekintéséhez.');
        }

        $gridData = $this->generator->generateGrid(
            $crossword->getWords(),
        );

        return [
            'crossword' => $crossword,
            'grid' => $gridData['grid'],
            'width' => $gridData['width'],
            'height' => $gridData['height'],
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
                $crossword->getWords(),
            );

            return [
                'crossword' => $crossword,
                'grid' => $gridData['grid'],
                'width' => $gridData['width'],
                'height' => $gridData['height'],
            ];
        });
    }

    public function create(array $data): array
    {
        if (!empty($data['entries'])) {
            $placements = $this->resolveExplicitPlacements($data['entries']);

            $validation = $this->placementValidator->validateLayout($placements);

            if (!$validation->valid) {
                throw new InvalidCrosswordLayout($validation->errors);
            }
        } else {
            $placements = $this->resolveLegacyPlacements($data['main_solution'], $data['clue_ids']);
        }

        return $this->persistCrossword($data, $placements);
    }

    /**
     * Létrehozza a Placement objektumokat a felhasználó által megadott elhelyezési adatok alapján.
     *
     * @param array<array<string, mixed>> $entries - A felhasználó által megadott elhelyezési adatok tömbje.
     * @return array<Placement> - A létrehozott Placement objektumok tömbje.
     * @throws Exception - Ha a megadott clue_id nem létezik.
     */
    private function resolveExplicitPlacements(array $entries): array
    {
        $clueIds = collect($entries)->pluck('clue_id')->map(fn($id) => (int)$id)->all();

        $cluesById = Clue::query()->whereIn('id', $clueIds)->get()->keyBy('id');

        return collect($entries)->map(function (array $entry) use ($cluesById) {
            $clueId = (int) $entry['clue_id'];
            $clue = $cluesById->get($clueId);

            if (!$clue) {
                throw new Exception('A megadott clue_id nem létezik: ' . $clueId);
            }

            return new Placement(
                id: null,
                clueId: $clue->id,
                answer: $clue->solution,
                direction: Direction::from($entry['direction']),
                startRow: (int) $entry['start_row'],
                startCol: (int) $entry['start_col'],
            );
        })->values()->all();
    }

    /**
     * Legacy kód, a régi "clue_ids" mező alapján hozza létre a Placement objektumokat.
     * 
     * @param string $mainSolution - A főmegoldás szava.
     * @param array<int> $clueIds - A felhasználó által megadott clue_id-k tömbje.
     * @return array<Placement> - A létrehozott Placement objektumok tömbje.
     * @throws Exception - Ha a megadott clue_id nem létezik, vagy ha a generatedPlacementsFixedOrder metódus hibát dob.
     */
    private function resolveLegacyPlacements(string $mainSolution, array $clueIds): array
    {
        $cluesById = Clue::query()->whereIn('id', $clueIds)->get()->keyBy('id');

        $orderedClues = collect($clueIds)->map(function ($clueId) use ($cluesById) {
            $clue = $cluesById->get((int) $clueId);

            if (!$clue) {
                throw new Exception('A megadott clue_id nem létezik: ' . $clueId);
            }

            return $clue;
        })->values();

        $generated = $this->generator->generatePlacementsFixedOrder(
            mb_strtoupper($mainSolution),
            $orderedClues,
        );

        return collect($generated)->map(function (array $generatedPlacement) use ($cluesById) {
            $clue = $cluesById->get((int) $generatedPlacement['clue_id']);

            $direction = $generatedPlacement['direction'];

            if (is_string($direction)) {
                $direction = Direction::from($direction);
            }

            return new Placement(
                id: null,
                clueId: $clue->id,
                answer: $clue->solution,
                direction: $direction,
                startRow: (int) $generatedPlacement['start_row'],
                startCol: (int) $generatedPlacement['start_col'],
            );
        })->values()->all();
    }

    /**
     * Menti a keresztrejtvényt és az elhelyezéseket az adatbázisba.
     * 
     * @param array<string, mixed> $data - A keresztrejtvény adatai.
     * @param array<Placement> $placements - A keresztrejtvény elhelyezései.
     * @return array<string, mixed> - A mentett keresztrejtvény és a generált rács adatai.
     */
    private function persistCrossword(array $data, array $placements): array
    {
        return DB::transaction(function () use ($data, $placements) {
            $crossword = Crossword::create([
                'title' => trim($data['title']),
                'main_solution' => isset($data['main_solution']) ? mb_strtoupper($data['main_solution']) : null,
                'user_id' => $data['user_id'],
                'difficulty' => $data['difficulty'] ?? Difficulty::EASY,
                'is_public' => $data['is_public'] ?? false,
            ]);

            $crossword->topics()->sync($data['topic_ids'] ?? []);

            foreach ($placements as $placement) {
                $crossword->crosswordClues()->create([
                    'clue_id' => $placement->clueId,
                    'direction' => $placement->direction,
                    'start_row' => $placement->startRow,
                    'start_col' => $placement->startCol,
                    // Legacy kód support, el kell majd távolítani
                    'intersection_index' => null,
                    'is_main' => false,
                ]);
            }

            $crossword->load([
                'crosswordClues.clue',
                'creator',
                'topics',
            ]);

            $gridData = $this->generator->generateGrid($crossword->getWords());

            return [
                'crossword' => $crossword,
                'grid' => $gridData['grid'],
                'width' => $gridData['width'],
                'height' => $gridData['height'],
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
            ])
            ->where('is_public', true);

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

    public function validateWordForGuest(int $crosswordId, int $wordIndex, string $userInput): bool
    {
        $crossword = Crossword::with('crosswordClues.clue')->findOrFail($crosswordId);

        $clue = $crossword->crosswordClues->where('is_main', false)->values()->get($wordIndex)?->clue;

        if (!$clue) {
            throw new Exception('A megadott szóindex nem létezik a keresztrejtvényben.');
        }

        return mb_strtoupper($userInput) === mb_strtoupper($clue->solution);
    }

    public function toggleVisibility(int $crosswordId, int $userId): Crossword
    {
        $crossword = Crossword::findOrFail($crosswordId);

        if ($crossword->user_id !== $userId) {
            throw new Exception('Más rejtvényének a láthatóságát nem módosíthatod.');
        }

        $crossword->is_public = !$crossword->is_public;
        $crossword->save();

        return $crossword;
    }

    public function getForEdit(int $id, int $userId): array
    {
        $crossword = Crossword::with([
            'topics:id,name',
            'crosswordClues' => function ($query) {
                $query->where('is_main', false)->orderBy('start_row', 'asc');
            },
            'crosswordClues.clue:id,solution,definition'
        ])
        ->withCount(['attempts' => function ($query) {
            $query->where('status', '!=', 'not_started');
        }])
        ->findOrFail($id);

        if ($crossword->user_id !== $userId) {
            throw new Exception('Nincs jogosultságod a rejtvény megtekintéséhez/szerkesztéséhez.');
        }

        return [
            'id' => $crossword->id,
            'title' => $crossword->title,
            'main_solution' => $crossword->main_solution,
            'difficulty' => $crossword->difficulty?->value ?? $crossword->difficulty,
            'is_public' => (bool) $crossword->is_public,
            'attempts_count' => $crossword->attempts_count,
            'topics' => $crossword->topics->map(fn($t) => ['id' => $t->id, 'name' => $t->name]),
            'clues' => $crossword->crosswordClues->map(fn($cc) => [
                'id' => $cc->clue->id,
                'solution' => $cc->clue->solution,
                'definition' => $cc->clue->definition,
            ])->values(),
            'entries' => $crossword->crosswordClues->map(fn ($entry) => [
                'id' => $entry->id,
                'clue_id' => $entry->clue->id,
                'solution' => $entry->clue->solution,
                'definition' => $entry->clue->definition,
                'direction' => $entry->getDirection(),
                'start_row' => $entry->start_row,
                'start_col' => $entry->start_col,
            ])->values(),
        ];
    }

    public function updateCrossword(int $id, int $userId, array $data): Crossword
    {
        return DB::transaction(function () use ($id, $userId, $data) {
            $crossword = Crossword::where('id', $id)->lockForUpdate()->firstOrFail();

            if ($crossword->user_id !== $userId) {
                throw new Exception('Más rejtvényét nem módosíthatod.');
            }

            $hasRealAttempts = $crossword->attempts()->where('status', '!=', 'not_started')->exists();

            if ($crossword->is_public || $hasRealAttempts) {
                throw new Exception('Ez a rejtvény már nyilvános vagy rendelkezik próbálkozásokkal, így nem módosítható.');
            }

            if (!empty($data['entries'])) {
                $placements = $this->resolveExplicitPlacements($data['entries']);

                $validation = $this->placementValidator->validateLayout($placements);

                if (!$validation->valid) {
                    throw new InvalidCrosswordLayout($validation->errors);
                }
            } else {
                $placements = $this->resolveLegacyPlacements($data['main_solution'], $data['clue_ids']);
            }

            // Alapadatok frissítése
            $crossword->update([
                'title' => $data['title'],
                'main_solution' => isset($data['main_solution']) ? mb_strtoupper($data['main_solution']) : null,
                'difficulty' => $data['difficulty'] ?? 'easy',
                'is_public' => $data['is_public'] ?? false,
            ]);

            // Témák frissítése
            if (isset($data['topic_ids'])) {
                $crossword->topics()->sync($data['topic_ids']);
            }

            // Régi elhelyezések törlése és újragenerálása
            $crossword->crosswordClues()->delete();

            foreach ($placements as $placement) {
                $crossword->crosswordClues()->create([
                    'clue_id' => $placement->clueId,
                    'direction' => $placement->direction,
                    'start_row' => $placement->startRow,
                    'start_col' => $placement->startCol,
                    // Legacy kód support, el kell majd távolítani
                    'intersection_index' => null,
                    'is_main' => false,
                ]);
            }

            return $crossword->load([
                'crosswordClues.clue',
                'creator',
                'topics',
            ]);
        });
    }

    public function deleteCrossword(int $id, int $userId): void
    {
        $crossword = Crossword::findOrFail($id);

        if ($crossword->user_id !== $userId) {
            throw new Exception('Más rejtvényét nem törölheted.');
        }

        $crossword->delete();
    }
}