<?php

namespace App\Services;

use App\Models\Clue;
use App\Models\Crossword;
use Illuminate\Support\Facades\DB;
use Exception;
use App\Enums\Direction;
use App\Domain\Crossword\Placement;
use App\Services\PlacementValidator;
use App\Exceptions\InvalidCrosswordLayout;
use App\Enums\Difficulty;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

class CrosswordService
{
    public function __construct(
        private readonly CrosswordGenerator $generator,
        private readonly PlacementValidator $placementValidator,
        private readonly CrosswordPublicationValidator $publicationValidator,
        private readonly CrosswordTopicConsistencyValidator $topicConsistencyValidator,
    ) {
    }

    public function getById(int $id): array
    {
        $crossword = Crossword::query()
            ->whereKey($id)
            ->where('is_public', true)
            ->with([
                'crosswordClues.clue',
                'creator',
                'topics',
            ])
            ->findOrFail($id);

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

    public function create(array $data): array
    {
        if (!empty($data['entries'])) {
            $clueIds = array_column($data['entries'] ?? [], 'clue_id');
            $topic_ids = $data['topic_ids'] ?? [];

            $this->topicConsistencyValidator->assertValid($clueIds, $topic_ids);

            $placements = $this->resolveExplicitPlacements($data['entries']);

            $validation = $this->placementValidator->validateLayout($placements);

            if (!$validation->valid) {
                throw new InvalidCrosswordLayout($validation->errors);
            }
        } else {
            $clueIds = $data['clue_ids'] ?? [];
            $topic_ids = $data['topic_ids'] ?? [];

            $this->topicConsistencyValidator->assertValid($clueIds, $topic_ids);

            $placements = $this->resolveLegacyPlacements($data['main_solution'], $data['clue_ids']);
        }

        $placements = $this->normalizePlacements($placements);

        return $this->persistCrossword($data, $placements);
    }

    /**
     * Létrehozza a Placement objektumokat a felhasználó által megadott elhelyezési adatok alapján.
     *
     * @param array<array<string, mixed>> $entries - A felhasználó által megadott elhelyezési adatok tömbje.
     * @return array<Placement> - A létrehozott Placement objektumok tömbje.
     * @throws ModelNotFoundException - Ha a megadott clue_id nem létezik.
     */
    private function resolveExplicitPlacements(array $entries): array
    {
        $clueIds = collect($entries)->pluck('clue_id')->map(fn($id) => (int)$id)->all();

        $cluesById = Clue::query()->whereIn('id', $clueIds)->get()->keyBy('id');

        return collect($entries)->map(function (array $entry) use ($cluesById) {
            $clueId = (int) $entry['clue_id'];
            $clue = $cluesById->get($clueId);

            if (!$clue) {
                throw new ModelNotFoundException('A megadott clue_id nem létezik: ' . $clueId);
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
     * @throws ModelNotFoundException - Ha a megadott clue_id nem létezik.
     * @throws Exception - Ha a megadott clue_id nem létezik, vagy ha a generatedPlacementsFixedOrder metódus hibát dob.
     */
    private function resolveLegacyPlacements(string $mainSolution, array $clueIds): array
    {
        $cluesById = Clue::query()->whereIn('id', $clueIds)->get()->keyBy('id');

        $orderedClues = collect($clueIds)->map(function ($clueId) use ($cluesById) {
            $clue = $cluesById->get((int) $clueId);

            if (!$clue) {
                throw new ModelNotFoundException('A megadott clue_id nem létezik: ' . $clueId);
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
                'is_public' => false,
            ]);

            $crossword->topics()->sync($data['topic_ids'] ?? []);

            foreach ($placements as $placement) {
                $crossword->crosswordClues()->create([
                    'clue_id' => $placement->clueId,
                    'direction' => $placement->direction,
                    'start_row' => $placement->startRow,
                    'start_col' => $placement->startCol,
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
                'crosswordClues as words_count',
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
                throw new AuthorizationException('A státusz szűrő csak bejelentkezett felhasználók számára elérhető.');
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
                    throw new InvalidArgumentException('Ismeretlen státusz szűrő: ' . $filters['status']); 
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

    public function validateEntryForGuest(int $crosswordId, int $placementId, string $userInput): bool
    {
        $crossword = Crossword::query()->with('crosswordClues.clue')->findOrFail($crosswordId);

        if (!$crossword->is_public) {
            throw new AuthorizationException('A rejtvény nem nyilvános, így vendégként nem próbálható ki.');
        }

        $clue = $crossword->crosswordClues->findOrFail($placementId)->clue;

        return mb_strtoupper($userInput) === mb_strtoupper($clue->solution);
    }

    public function setVisibility(int $crosswordId, int $userId, bool $isPublic): Crossword
    {
        return DB::transaction(function () use ($crosswordId, $userId, $isPublic) {
            $crossword = Crossword::query()->whereKey($crosswordId)->lockForUpdate()->firstOrFail();

            if ($crossword->user_id !== $userId) {
                throw new AuthorizationException('Más rejtvényének a láthatóságát nem módosíthatod.');
            }

            if ($crossword->is_public === $isPublic) {
                return $crossword;
            }

            $hasRealAttempts = $crossword->attempts()->where('status', '!=', 'not_started')->exists();

            if (!$isPublic && $hasRealAttempts) {
                throw new ConflictHttpException('Ez a rejtvény már rendelkezik próbálkozásokkal, így nem tehető priváttá.');
            }

            if ($isPublic) {
                $this->publicationValidator->assertPublishable($crossword);
            }

            $crossword->update([
                'is_public' => $isPublic,
            ]);

            return $crossword->refresh();
        });
    }

    public function getForEdit(int $id, int $userId): array
    {
        $crossword = Crossword::with([
            'topics:id,name',
            'crosswordClues',
            'crosswordClues.clue:id,solution,definition'
        ])
        ->withCount(['attempts' => function ($query) {
            $query->where('status', '!=', 'not_started');
        }])
        ->findOrFail($id);

        if ($crossword->user_id !== $userId) {
            throw new AuthorizationException('Nincs jogosultságod a rejtvény megtekintéséhez/szerkesztéséhez.');
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
                throw new AuthorizationException('Más rejtvényét nem módosíthatod.');
            }

            $hasRealAttempts = $crossword->attempts()->where('status', '!=', 'not_started')->exists();

            if ($crossword->is_public || $hasRealAttempts) {
                throw new ConflictHttpException('Ez a rejtvény már nyilvános vagy rendelkezik próbálkozásokkal, így nem módosítható.');
            }

            if (!empty($data['entries'])) {
                $clueIds = array_column($data['entries'] ?? [], 'clue_id');
                $topic_ids = $data['topic_ids'] ?? [];

                $this->topicConsistencyValidator->assertValid($clueIds, $topic_ids);

                $placements = $this->resolveExplicitPlacements($data['entries']);

                $validation = $this->placementValidator->validateLayout($placements);

                if (!$validation->valid) {
                    throw new InvalidCrosswordLayout($validation->errors);
                }
            } else {
                $clueIds = $data['clue_ids'] ?? [];
                $topic_ids = $data['topic_ids'] ?? [];

                $this->topicConsistencyValidator->assertValid($clueIds, $topic_ids);

                $placements = $this->resolveLegacyPlacements($data['main_solution'], $data['clue_ids']);
            }

            $placements = $this->normalizePlacements($placements);

            // Alapadatok frissítése
            $crossword->update([
                'title' => $data['title'],
                'main_solution' => isset($data['main_solution']) ? mb_strtoupper($data['main_solution']) : null,
                'difficulty' => $data['difficulty'] ?? 'easy',
                'is_public' => false,
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
        DB::transaction(function () use ($id, $userId) {
            $crossword = Crossword::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($crossword->user_id !== $userId) {
                throw new AuthorizationException('Más rejtvényét nem törölheted.');
            }

            $hasRealAttempts = $crossword->attempts()->where('status', '!=', 'not_started')->exists();

            if ($crossword->is_public || $hasRealAttempts) {
                throw new ConflictHttpException('Ez a rejtvény nyilvános vagy már rendelkezik megkezdett próbálkozásokkal, így nem törölhető.');
            }

            $crossword->delete();
        });
    }

    /**
     * Normalizálja az elhelyezések koordinátáit úgy, hogy a legkisebb sor- és oszlopszám 0 legyen.
     * 
     * @param array<Placement> $placements - Az elhelyezések tömbje.
     * @return array<Placement> - A normalizált elhelyezések tömbje.
     */
    private function normalizePlacements(array $placements): array
    {
        if ($placements === []) {
            return [];
        } // placementet atadni

        $minRow = min(array_map(
            fn (Placement $placement): int => $placement->startRow,
            $placements,
        ));

        $minCol = min(array_map(
            fn (Placement $placement): int => $placement->startCol,
            $placements,
        ));

        return array_map(
            fn (Placement $placement): Placement => new Placement(
                id: $placement->id,
                clueId: $placement->clueId,
                answer: $placement->answer,
                direction: $placement->direction,
                startRow: $placement->startRow - $minRow,
                startCol: $placement->startCol - $minCol,
            ),
            $placements,
        );
    }
}