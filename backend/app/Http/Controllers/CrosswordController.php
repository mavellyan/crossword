<?php

namespace App\Http\Controllers;

use App\Services\CrosswordService;
use App\Models\CrosswordAttempt;
use App\Http\Resources\CrosswordResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class CrosswordController extends Controller
{
    public function __construct(
        private readonly CrosswordService $crosswordService,
    ) {
    }

    public function getCrossword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'required|integer|exists:crosswords,id',
        ]);

        $user = $request->user('sanctum');

        $result = $this->crosswordService->getById($validated['id'], $user?->id);

        $attempt = $result['attempt'];

        return response()->json([
            'success' => true,
            'crossword' => new CrosswordResource($result),
            'attempt' => $user === null ? null : [
                'id' => $attempt->id,
                'status' => $attempt->status,
                'state_version' => $attempt->state_version,
                'word_inputs' => data_get($attempt->grid_state, 'word_inputs', []),
                'elapsed_time' => $attempt->elapsed_time,
                'started_at' => $attempt->started_at,
            ],
        ]);
    }

    public function createCrossword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|min:5|max:255',
            'main_solution' => 'required|string|min:3|max:20|regex:/^[A-ZÁÉÍÓÖŐÚÜŰ]+$/u',
            'clue_ids' => 'required|array|min:1',
            'clue_ids.*' => 'required|integer|exists:clues,id',
            'topic_ids' => 'nullable|array',
            'topic_ids.*' => 'nullable|integer|exists:topics,id',
            'difficulty' => 'nullable|string',
            'is_public' => 'nullable|boolean',
        ]);

        $validated['user_id'] = $request->user()?->id;

        try {
            $result = $this->crosswordService->createFromClueIds($validated);

            return response()->json([
                'success' => true,
                'crossword' => new CrosswordResource($result),
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function listCrosswords(Request $request): JsonResponse
    {
        $user = $request->user('sanctum');

        $list = $this->crosswordService->getAllForList([
            'search' => $request->input('search'),
            'topics' => $request->input('topics'),
            'difficulties' => $request->input('difficulties'),
            'creators' => $request->input('creators'),
            'sortOrder' => $request->input('sortOrder', 'dateDesc'),
            'status' => $user === null ? 'status_all' : $request->input('status', 'status_all'),
            'userId' => $user?->id,
        ]);

        $crosswords = $list->map(function ($crossword) use ($user) {
            $crossword->load('creator', 'topics');
            return [
                'id' => $crossword->id,
                'title' => $crossword->title,
                'main_solution' => $crossword->main_solution,
                'difficulty' => $crossword->difficulty?->value ?? $crossword->difficulty,
                'is_public' => (bool) $crossword->is_public,
                'words_count' => $crossword->words_count,
                'creator' => $crossword->creator ? [
                    'id' => $crossword->creator->id,
                    'username' => $crossword->creator->username,
                ] : null,
                'created_at' => $crossword->created_at?->toDateTimeString(),
                'status' => $user === null ? null : $crossword->attempts()->where('user_id', $user->id)->latest('id')->first()?->status,
                'topics' => $crossword->topics->map(function ($topic) {
                    return [
                        'id' => $topic->id,
                        'name' => $topic->name,
                    ];
                }),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'crosswords' => $crosswords,
        ]);
    }

    public function saveProgress(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'attempt_id' => 'required|integer|exists:crossword_attempts,id',
            'state_version' => 'required|integer',
            'word_inputs' => 'required|array',
            'word_inputs.*' => 'required|array',
            'word_inputs.*.*' => 'nullable|string|max:1',
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'A felhasználó nincs bejelentkezve.',
            ], 401);
        }

        $attempt = CrosswordAttempt::query()
            ->whereKey($validated['attempt_id'])
            ->where('user_id', $user->id)
            ->firstOrFail();

        if ($attempt->status !== 'in_progress') {
            return response()->json([
                'success' => false,
                'message' => 'Ez a próbálkozás már be van fejezve, nem lehet menteni a folyamatot.',
            ], 409);
        }

        if ((int) $attempt->state_version !== (int) $validated['state_version']) {
            return response()->json([
                'success' => false,
                'message' => 'A próbálkozás állapota megváltozott, frissítsd az oldalt és próbáld újra.',
            ], 409);
        }

        $placements = $attempt->crossword->getWords()->values();

        $submittedInputs = $validated['word_inputs'];
        $normalizedInputs = [];
        $isCompleted = true;

        foreach ($placements as $placement) {
            $placementId = $placement->id;

            $expectedSolution = mb_strtoupper($placement->getSolution());
            $expectedLength = mb_strlen($expectedSolution);

            $submittedCells = array_values($submittedInputs[$placementId] ?? []);
            $normalizedCells = [];

            for ($index = 0; $index < $expectedLength; $index++) {
                $cellValue = $submittedCells[$index] ?? '';

                if ($cellValue === null || $cellValue === '') {
                    $normalizedCells[] = '';
                    continue;
                }

                $normalizedCells[] = mb_substr(mb_strtoupper((string) $cellValue), 0, 1);
            }

            $normalizedInputs[$placementId] = $normalizedCells;

            if (implode('', $normalizedCells) !== $expectedSolution) {
                $isCompleted = false;
            }
        }

        $attempt->grid_state = [
            'word_inputs' => $normalizedInputs,
        ];

        $attempt->state_version++;

        if ($isCompleted) {
            if ($attempt->started_at !== null) {
                $attempt->elapsed_time += $attempt->started_at->diffInSeconds(now());
                $attempt->started_at = null;
            }

            $attempt->status = 'completed';
            $attempt->completed_at = now();
        }

        $attempt->save();

        return response()->json([
            'success' => true,
            'message' => 'Mentés sikeres!',
            'attempt' => [
                'id' => $attempt->id,
                'status' => $attempt->status,
                'state_version' => $attempt->state_version,
                'elapsed_time' => $attempt->elapsed_time,
                'started_at' => $attempt->started_at,
            ],
        ]);
    }

    public function startAttempt(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'attempt_id' => 'nullable|integer|exists:crossword_attempts,id',
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'A felhasználó nincs bejelentkezve.',
            ], 401);
        }

        $attempt = CrosswordAttempt::query()
            ->whereKey($validated['attempt_id'])
            ->where('user_id', $user->id)
            ->firstOrFail();

        if ($attempt->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Ez a próbálkozás már be van fejezve.',
            ], 409);
        }

        if ($attempt->status === 'not_started') {
            $attempt->status = 'in_progress';
        }

        $attempt->started_at = now();
        $attempt->save();

        return response()->json([
            'success' => true,
            'attempt' => [
                'id' => $attempt->id,
                'status' => $attempt->status,
                'state_version' => $attempt->state_version,
                'elapsed_time' => $attempt->elapsed_time,
                'started_at' => $attempt->started_at,
            ],
        ]);
    }

    public function stopAttempt(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'attempt_id' => 'required|integer|exists:crossword_attempts,id',
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'A felhasználó nincs bejelentkezve.',
            ], 401);
        }

        $attempt = CrosswordAttempt::query()
            ->whereKey($validated['attempt_id'])
            ->where('user_id', $user->id)
            ->firstOrFail();

        if ($attempt->status !== 'in_progress') {
            return response()->json([
                'success' => false,
                'message' => 'Ez a próbálkozás már be van fejezve, nem lehet leállítani.',
            ], 409);
        }

        if ($attempt->started_at !== null) {
            $attempt->elapsed_time += $attempt->started_at->diffInSeconds(now());
            $attempt->started_at = null;
        }

        $attempt->save();

        return response()->json([
            'success' => true,
            'attempt' => [
                'id' => $attempt->id,
                'status' => $attempt->status,
                'state_version' => $attempt->state_version,
                'elapsed_time' => $attempt->elapsed_time,
                'started_at' => $attempt->started_at,
            ],
        ]);
    }

    public function abandonAttempt(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'attempt_id' => 'required|integer|exists:crossword_attempts,id',
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'A felhasználó nincs bejelentkezve.',
            ], 401);
        }

        $attempt = CrosswordAttempt::query()
            ->whereKey($validated['attempt_id'])
            ->where('user_id', $user->id)
            ->firstOrFail();

        if (!in_array($attempt->status, ['in_progress', 'completed'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Ez a próbálkozás még nincs elkezdve, vagy már el van dobva, nem lehet eldobni.',
            ], 409);
        }

        // Ha a próbálkozás már be van fejezve, akkor nem kell semmit csinálni, csak jelezzük, hogy új próbálkozást kell indítani.
        if ($attempt->status === 'completed') {
            $newAttempt = CrosswordAttempt::create([
                'user_id' => $user->id,
                'crossword_id' => $attempt->crossword_id,
                'status' => 'not_started',
                'grid_state' => [
                    'word_inputs' => [],
                ],
                'state_version' => 0,
                'elapsed_time' => 0,
            ]);

            $newAttempt->save();

            return response()->json([
                'success' => true,
                'message' => 'Befejezett próbálkozás, létrehoztunk egy újat.',
            ], 200);
        }

        $attempt->status = 'abandoned';
        $attempt->abandoned_at = now();
        $attempt->save();

        return response()->json([
            'success' => true,
            'message' => 'Próbálkozás sikeresen törölve.',
        ]);
    }
}