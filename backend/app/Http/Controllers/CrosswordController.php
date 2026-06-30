<?php

namespace App\Http\Controllers;

use App\Services\CrosswordService;
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

    public function getCrossword(int $id): JsonResponse
    {
        $result = $this->crosswordService->getById($id);

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

        return response()->json([
            'success' => true,
            'crosswords' => $crosswords->map(function ($crossword) {
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
                ];
            })->values(),
        ]);
    }
}