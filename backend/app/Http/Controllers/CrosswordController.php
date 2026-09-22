<?php

namespace App\Http\Controllers;

use App\Services\CrosswordService;
use App\Http\Resources\CrosswordResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;
use App\Exceptions\InvalidCrosswordLayout;
use App\Http\Requests\StoreCrosswordRequest;
use App\Http\Requests\UpdateCrosswordRequest;

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

        return response()->json([
            'success' => true,
            'crossword' => new CrosswordResource($result),
        ]);
    }

    public function createCrossword(StoreCrosswordRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        try {
            $result = $this->crosswordService->create($data);

            return response()->json([
                'success' => true,
                'crossword' => new CrosswordResource($result),
            ], 201);
        } catch (InvalidCrosswordLayout $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->layoutErrors,
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

    public function validateWordForGuest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'crossword_id' => 'required|integer|exists:crosswords,id',
            'word_index' => 'required|integer|min:0',
            'user_input' => 'required|string|min:1|max:20|regex:/^[A-ZÁÉÍÓÖŐÚÜŰ]+$/u',
        ]);

        try {
            $isCorrect = $this->crosswordService->validateWordForGuest(
                $validated['crossword_id'],
                $validated['word_index'],
                $validated['user_input']
            );

            return response()->json([
                'success' => true,
                'is_correct' => $isCorrect,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Hiba történt a szó érvényesítésekor: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function toggleVisibility(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'required|integer|exists:crosswords,id',
        ]);

        $user = $request->user('sanctum');

        try {
            $result = $this->crosswordService->toggleVisibility($validated['id'], $user->id);

            return response()->json([
                'success' => true,
                'is_public' => (bool) $result->is_public,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Hiba történt a rejtvény láthatóságának váltásakor: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getCrosswordForEdit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'required|integer|exists:crosswords,id',
        ]);

        $user = $request->user('sanctum');

        try {
            $result = $this->crosswordService->getForEdit($validated['id'], $user->id);

            return response()->json([
                'success' => true,
                'crossword' => $result,  
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    public function updateCrossword(UpdateCrosswordRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        $user = $request->user('sanctum');

        try {
            $crossword = $this->crosswordService->updateCrossword($data['id'], $user->id, $data);

            return response()->json([
                'success' => true,
                'crossword' => $crossword,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        }
    }

    public function deleteCrossword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'required|integer|exists:crosswords,id',
        ]);

        $user = $request->user('sanctum');

        try {
            $this->crosswordService->deleteCrossword($validated['id'], $user->id);

            return response()->json([
                'success' => true,
                'message' => 'A rejtvény sikeresen törölve.',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Hiba történt a rejtvény törlésekor: ' . $e->getMessage(),
            ], 403);
        }
    }
}