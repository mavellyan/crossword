<?php

namespace App\Http\Controllers;

use App\Services\CrosswordService;
use App\Models\CrosswordAttempt;
use App\Http\Resources\CrosswordResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use SebastianBergmann\Type\TrueType;
use Throwable;

class CrosswordController extends Controller
{
    public function __construct(
        private readonly CrosswordService $crosswordService,
    ) {
    }

    public function getCrossword(Request $request): JsonResponse
    {
        $id = $request->input('id');
        $user_id = $request->input('user_id');

        $result = $this->crosswordService->getById($id, $user_id);
        
        return response()->json([
            'success' => true,
            'crossword' => new CrosswordResource($result),
        ]);
    }

    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|min:5|max:255',
            'main_solution' => 'required|string|min:3|max:20|regex:/^[A-ZÁÉÍÓÖŐÚÜŰ]+$/u',
            'clue_ids' => 'required|array|min:1',
            'clue_ids.*' => 'required|integer|exists:clues,id',
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

        $user_id = $request->input('user_id');

        return response()->json([
            'success' => true,
            'crosswords' => $crosswords->map(function ($crossword) use ($user_id) {
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
                    'status' => $crossword->attempts()->latest('id')->first()?->status,
                ];
            })->values(),
        ]);
    }

    public function saveProgress(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'crossword_id' => 'required|integer|exists:crosswords,id',
            'user_id' => 'required|integer|exists:users,id',
            'words' => 'required|array',
            'grid' => 'required|array',
        ]);

        $attempt = CrosswordAttempt::query()
            ->where('user_id', $validated['user_id'])
            ->where('crossword_id', $validated['crossword_id'])
            ->where('status', 'in_progress')
            ->latest('id')
            ->first();

        if (!$attempt) {
            return response()->json([
                'success' => false,
                'message' => 'Nincs folyamatban rejtvény próbálkozás, de menteni próbálunk?',
            ], 404);
        }

        $attempt->grid_state = [
            'cells' => $validated['grid'],
        ];

        $correctWords = 0;
        $solutions = $attempt->crossword->getWords()->pluck('solution')->toArray();
        foreach ($validated['words'] as $word) {
            if (in_array(mb_strtolower($word), $solutions)) {
                $correctWords++;
            }
        }

        if ($correctWords === count($attempt->crossword->getWords())) {
            $attempt->status = 'completed';
            $attempt->completed_at = now();
        }

        $attempt->state_version = $attempt->state_version + 1;

        $attempt->save();

        return response()->json([
            'success' => true,
            'message' => 'Mentés sikeres!',
            'status' => $attempt->status,
        ]);
    }
}