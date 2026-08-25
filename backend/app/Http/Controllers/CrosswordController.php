<?php

namespace App\Http\Controllers;

use App\Services\CrosswordService;
use App\Models\CrosswordAttempt;
use App\Http\Resources\CrosswordResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

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
            'attempt' => $user == null ? null : [
                'id' => $attempt->id,
                'status' => $attempt->status,
                'state_version' => $attempt->state_version,
                'word_inputs' => data_get($attempt->grid_state, 'word_inputs', []),
            ],
        ]);
    }

    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|min:5|max:255',
            'main_solution' => 'required|string|min:3|max:20|regex:/^[A-ZÁÉÍÓÖŐÚÜŰ]+$/u',
            'clue_ids' => 'required|array|min:1',
            'clue_ids.*' => 'required|integer|exists:clues,id',
            'topic_id' => 'nullable|integer|exists:topics,id',
            'difficulty' => 'nullable|string',
            'is_public' => 'nullable|boolean',
        ]);

        $validated['creator_user_id'] = $request->user()?->id;

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
        $crosswords = $this->crosswordService->getAllForList([
            'search' => $request->input('search'),
        ]);

        $user = $request->user('sanctum');

        return response()->json([
            'success' => true,
            'crosswords' => $crosswords->map(function ($crossword) use ($user) {
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
                    'status' => $user === null ? null :
                        $crossword->attempts()->where('user_id', $user->id)->latest('id')->first()?->status,
                ];
            })->values(),
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
            ],
        ]);
    }
}