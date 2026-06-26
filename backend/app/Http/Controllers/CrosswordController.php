<?php

namespace App\Http\Controllers;

use App\Services\CrosswordService;
use App\Http\Resources\CrosswordResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class CrosswordController extends Controller
{
    public function __construct(
        private readonly CrosswordService $crosswordService,
    ) {
    }

    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|min:3|max:255',
            'main_solution' => 'required|string|min:3|max:20',
            'difficulty' => 'nullable|string',
            'is_public' => 'nullable|boolean',

            'word_pairs' => 'required|array|min:3',
            'word_pairs.*.definition' => 'required|string|max:255',
            'word_pairs.*.solution' => 'required|string|min:2|max:50',
        ]);

        $validated['creator_user_id'] = $request->user()->id;

        try {
            $result = $this->crosswordService->create($validated);

            return response()->json([
                'success' => true,
                'crossword' => $result['crossword'],
                'grid' => $result['grid'],
                'width' => $result['width'],
                'height' => $result['height'],
                'solution_col' => $result['solution_col'],
                'main_solution' => $result['main_solution'],
            ], 201);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getCrossword(int $id): JsonResponse
    {
        $result = $this->crosswordService->getById($id);

        return response()->json([
            'success' => true,
            'crossword' => new CrosswordResource($result),
        ]);
    }
}