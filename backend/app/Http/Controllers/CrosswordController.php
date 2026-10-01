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
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Illuminate\Support\Facades\Log;
use LogicException;
use InvalidArgumentException;

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

        $result = $this->crosswordService->getById($validated['id']);

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
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'A megadott meghatározás(ok) nem található(k).',
            ], 404);
        } catch (LogicException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Hiba történt a rejtvény létrehozásakor: ' . $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            $this->logUnexpectedFailure(
                request: $request,
                operation: 'a rejtvény létrehozásakor',
                exception: $e,
                context: [
                    'user_id' => $request->user()?->getAuthIdentifier(),
                    'entry_count' => count($data['entries'] ?? $data['clue_ids'] ?? []),
                    'topic_ids' => $data['topic_ids'] ?? [],
                ],
            );

            return response()->json([
                'success' => false,
                'message' => 'Hiba történt a rejtvény létrehozásakor! Kérjük, próbálja meg újból!',
            ], 500);
        }
    }

    public function listCrosswords(Request $request): JsonResponse
    {
        $user = $request->user('sanctum');

        try {
            $list = $this->crosswordService->getAllForList([
                'search' => $request->input('search'),
                'topics' => $request->input('topics'),
                'difficulties' => $request->input('difficulties'),
                'creators' => $request->input('creators'),
                'sortOrder' => $request->input('sortOrder', 'dateDesc'),
                'status' => $user === null ? 'status_all' : $request->input('status', 'status_all'),
                'userId' => $user?->id,
            ]);
        } catch (AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (Throwable $e) {
            $this->logUnexpectedFailure(
                request: $request,
                operation: 'a rejtvények lekérdezésekor',
                exception: $e,
                context: [
                    'user_id' => $user?->getAuthIdentifier(),
                    'status_filter' => $request->input('status', 'status_all'),
                    'sort_order' => $request->input('sortOrder', 'dateDesc'),
                ],
            );

            return response()->json([
                'success' => false,
                'message' => 'Hiba történt a rejtvények lekérdezésekor! Kérjük, próbálja meg újból!',
            ], 500);
        }

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

    public function validateEntryForGuest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'crossword_id' => 'required|integer|exists:crosswords,id',
            'placement_id' => 'required|integer|min:0',
            'user_input' => 'required|string|min:1|max:20|regex:/^[A-Za-zÁÉÍÓÖŐÚÜŰáéíóöőúüű]+$/u',
        ]);

        try {
            $isCorrect = $this->crosswordService->validateEntryForGuest(
                $validated['crossword_id'],
                $validated['placement_id'],
                $validated['user_input']
            );

            return response()->json([
                'success' => true,
                'is_correct' => $isCorrect,
            ]);
        } catch (AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'A megadott szó nem található a keresztrejtvényben.',
            ], 404);
        } catch (Throwable $e) {
            $this->logUnexpectedFailure(
                request: $request,
                operation: 'a vendég megfejtésének ellenőrzésekor',
                exception: $e,
                context: [
                    'crossword_id' => $validated['crossword_id'],
                    'placement_id' => $validated['placement_id'],
                ],
            );

            return response()->json([
                'success' => false,
                'message' => 'Hiba történt a szó érvényesítésekor. Kérjük, próbálja meg újból!',
            ], 500);
        }
    }

    public function setVisibility(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'required|integer',
            'is_public' => 'required|boolean',
        ]);

        $user = $request->user('sanctum');
        $validated['is_public'] = $request->boolean('is_public');

        try {
            $result = $this->crosswordService->setVisibility($validated['id'], $user->id, $validated['is_public']);

            return response()->json([
                'success' => true,
                'is_public' => (bool) $result->is_public,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'A keresett rejtvény nem található.',
            ], 404);
        } catch (AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (ConflictHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        } catch (InvalidCrosswordLayout $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->layoutErrors,
            ], 422);
        } catch (Throwable $e) {
            $this->logUnexpectedFailure(
                request: $request,
                operation: 'a rejtvény láthatóságának módosításakor',
                exception: $e,
                context: [
                    'user_id' => $user?->getAuthIdentifier(),
                    'crossword_id' => $validated['id'],
                    'requested_visibility' => $validated['is_public'],
                ],
            );

            return response()->json([
                'success' => false,
                'message' => 'Hiba történt a rejtvény láthatóságának módosításakor. Kérjük, próbálja meg újból!',
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
        } catch (AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (Throwable $e) {
            $this->logUnexpectedFailure(
                request: $request,
                operation: 'a szerkesztendő rejtvény lekérdezésekor',
                exception: $e,
                context: [
                    'user_id' => $user?->getAuthIdentifier(),
                    'crossword_id' => $validated['id'],
                ],
            );

            return response()->json([
                'success' => false,
                'message' => 'Hiba történt a rejtvény lekérdezésekor! Kérjük, próbálja meg újból!',
            ], 500);
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
        } catch (InvalidCrosswordLayout $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->layoutErrors,
            ], 422);
        } catch (AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (ConflictHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        } catch (Throwable $e) {
            $this->logUnexpectedFailure(
                request: $request,
                operation: 'a rejtvény frissítésekor',
                exception: $e,
                context: [
                    'user_id' => $user?->getAuthIdentifier(),
                    'crossword_id' => $data['id'],
                    'entry_count' => count($data['entries'] ?? $data['clue_ids'] ?? []),
                    'topic_ids' => $data['topic_ids'] ?? [],
                ],
            );

            return response()->json([
                'success' => false,
                'message' => 'Hiba történt a rejtvény frissítésekor! Kérjük, próbálja meg újból!',
            ], 500);
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
        } catch (AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (ConflictHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        } catch (Throwable $e) {
            $this->logUnexpectedFailure(
                request: $request,
                operation: 'a rejtvény törlésekor',
                exception: $e,
                context: [
                    'user_id' => $user?->getAuthIdentifier(),
                    'crossword_id' => $validated['id'],
                ],
            );
            
            return response()->json([
                'success' => false,
                'message' => 'Hiba történt a rejtvény törlésekor! Kérjük, próbálja meg újból!',
            ], 500);
        }
    }

    /**
     * Naplózza a kezelt, de váratlan vezérlőhibát teljes kivétel-információval.
     *
     * @param array<string, mixed> $context
     */
    private function logUnexpectedFailure(
        Request $request,
        string $operation,
        Throwable $exception,
        array $context = [],
    ): void {
        Log::error('Váratlan hiba ' . $operation . '.', [
            ...$context,
            'exception' => $exception,
        ]);
    }
}