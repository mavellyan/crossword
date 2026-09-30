<?php

namespace App\Http\Controllers;

use App\Models\CrosswordAttempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\AttemptService;
use Throwable;
use App\Exceptions\AttemptStateConflict;
use App\Models\Crossword;
use App\Exceptions\CrosswordUnavailableException;
use Illuminate\Support\Facades\Log;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AttemptController extends Controller
{
    public function __construct(
        private readonly AttemptService $attemptService,
    ) {
    }

    public function getAttempt(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'crossword_id' => 'required|integer|exists:crosswords,id',
        ]);

        $user = $request->user('sanctum');

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'A felhasználó nincs bejelentkezve.',
            ], 401);
        }

        try {
            $result = $this->attemptService->getById($validated['crossword_id'], $user?->id);

            $attempt = $result['attempt'];

            return response()->json([
                'success' => true,
                'attempt' => $user === null ? null : [
                    'id' => $attempt->id, 
                    'status' => $attempt->status,
                    'state_version' => $attempt->state_version,
                    'cell_inputs' => (object) data_get($attempt->grid_state, 'cell_inputs', []),
                    'correct_entry_ids' => data_get($attempt->grid_state, 'correct_entry_ids', []),
                    'elapsed_time' => $attempt->elapsed_time,
                    'started_at' => $attempt->started_at,
                ],
                'best_time' => $result['best_time'],
            ]);
        } catch (AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (CrosswordUnavailableException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'A próbálkozás nem található.',
            ], 404);
        } catch (Throwable $e) {
            Log::error('Váratlan hiba a próbálkozás lekérdezésekor', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Hiba történt a próbálkozás lekérdezésekor! Kérjük, próbálja meg újból!',
            ], 500);
        }
    }

    public function startAttempt(Request $request): JsonResponse
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

        try {
            $attempt = $this->attemptService->startAttempt($validated['attempt_id'], $user->id);

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
        } catch (CrosswordUnavailableException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (AttemptStateConflict $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'A próbálkozás nem található.',
            ], 404);
        } catch (Throwable $e) {
            Log::error('Váratlan hiba a próbálkozás indításakor', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Hiba történt a próbálkozás indításakor! Kérjük, próbálja meg újból!',
            ], 500);
        }
    }

    public function stopAttempt(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'attempt_id' => 'required|integer|exists:crossword_attempts,id',
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json(['success' => false], 401);
        }

        try {
            $attempt = $this->attemptService->stopAttempt($validated['attempt_id'], $user->id);

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
        } catch (AttemptStateConflict $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'A próbálkozás nem található.',
            ], 404);
        } catch (Throwable $e) {
            Log::error('Váratlan hiba a próbálkozás leállításakor', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Hiba történt a próbálkozás leállításakor! Kérjük, próbálja meg újból!',
            ], 500);
        }
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

        try {
            $this->attemptService->abandonAttempt($validated['attempt_id'], $user->id);

            return response()->json([
                'success' => true,
                'message' => 'Próbálkozás sikeresen törölve.',
            ]);
        } catch (AttemptStateConflict $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'A próbálkozás nem található.',
            ], 404);
        } catch (Throwable $e) {
            Log::error('Váratlan hiba a próbálkozás törlésekor', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Hiba történt a próbálkozás törlésekor! Kérjük, próbálja meg újból!',
            ], 500);
        }
    }

    public function listBestAttempts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'crossword_id' => 'required|integer|exists:crosswords,id',
        ]);

        $crossword = Crossword::query()->findOrFail($validated['crossword_id']);

        if (!$crossword->is_public) {
            return response()->json([
                'success' => false,
                'message' => 'A rejtvény nem nyilvános, így a legjobb próbálkozások nem érhetők el.',
            ], 403);
        }

        try {
            $table = $this->attemptService->listBestAttempts($validated['crossword_id']);

            return response()->json([
                'success' => true,
                'table' => $table
            ]);
        } catch (Throwable $e) {
            Log::error('Váratlan hiba a legjobb próbálkozások lekérdezésekor', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Hiba történt a legjobb próbálkozások lekérdezésekor! Kérjük, próbálja meg újból!',
            ], 500);
        }
    }

    public function saveProgress(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'attempt_id' => 'required|integer|exists:crossword_attempts,id',
            'state_version' => 'required|integer',
            'cell_inputs' => 'present|array',
            'cell_inputs.*' => 'nullable|string|max:1',
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'A felhasználó nincs bejelentkezve.',
            ], 401);
        }

        $submittedInputs = $validated['cell_inputs'];

        try {
            $attempt = $this->attemptService->updateAttemptState($validated['attempt_id'], $user->id, $submittedInputs, $validated['state_version']);
        
            if ($attempt->status === 'completed') {
                $bestAttempt = CrosswordAttempt::query()
                    ->where('user_id', $user->id)
                    ->where('crossword_id', $attempt->crossword_id)
                    ->where('status', 'completed')
                    ->orderBy('elapsed_time', 'asc')
                    ->first();

                $bestTime = $bestAttempt ? $bestAttempt->elapsed_time : null;

                if ($bestTime === null || $attempt->elapsed_time < $bestTime) {
                    $bestTime = $attempt->elapsed_time;
                }
            }

            return response()->json([
                'success' => true,
                'save_status' => 'saved',
                'message' => 'Mentés sikeres!',
                'attempt' => [
                    'id' => $attempt->id,
                    'status' => $attempt->status,
                    'state_version' => $attempt->state_version,
                    'elapsed_time' => $attempt->elapsed_time,
                    'started_at' => $attempt->started_at,
                    'correct_entry_ids' => $attempt->grid_state['correct_entry_ids'] ?? [],
                ],
                'best_time' => $bestTime ?? null,
            ]);
        } catch (AttemptStateConflict $e) {
            return response()->json([
                'success' => false,
                'save_status' => 'conflict',
                'message' => $e->getMessage(),
            ], 409);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'save_status' => 'failed',
                'message' => 'A próbálkozás nem található.',
            ], 404);
        } catch (Throwable $e) {
            Log::error('Váratlan hiba a próbálkozás mentésekor', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'save_status' => 'failed',
                'message' => 'Hiba történt a próbálkozás mentésekor! Kérjük, próbálja meg újból!',
            ], 500);
        }
    }

    public function saveAndStopBeacon(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'attempt_id' => 'required|integer|exists:crossword_attempts,id',
            'state_version' => 'required|integer',
            'cell_inputs' => 'present|array',
            'cell_inputs.*' => 'nullable|string|max:1',
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json(['success' => false], 401);
        }

        $submittedInputs = $validated['cell_inputs']; 

        try {
            $this->attemptService->updateAttemptState($validated['attempt_id'], $user->id, $submittedInputs, $validated['state_version'], true);

            return response()->json(['success' => true,]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => false,], 404);
        } catch (Throwable $e) {
            Log::error('Váratlan hiba a próbálkozás mentésekor', ['error' => $e->getMessage()]);

            return response()->json(['success' => false], 500);
        }
    }
}