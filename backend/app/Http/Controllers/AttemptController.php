<?php

namespace App\Http\Controllers;

use App\Models\CrosswordAttempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\AttemptService;

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

        $result = $this->attemptService->getById($validated['crossword_id'], $user?->id);

        $attempt = $result['attempt'];

        return response()->json([
            'success' => true,
            'attempt' => $user === null ? null : [
                'id' => $attempt->id, 
                'status' => $attempt->status,
                'state_version' => $attempt->state_version,
                'word_inputs' => data_get($attempt->grid_state, 'word_inputs', []),
                'correct_words' => data_get($attempt->grid_state, 'correct_words', []),
                'elapsed_time' => $attempt->elapsed_time,
                'started_at' => $attempt->started_at,
            ],
            'best_time' => $result['best_time'],
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
            return response()->json(['success' => false], 401);
        }

        $attempt = CrosswordAttempt::query()
            ->whereKey($validated['attempt_id'])
            ->where('user_id', $user->id)
            ->firstOrFail();

        if (!$attempt || $attempt->status !== 'in_progress') {
            return response()->json(['success' => false], 409);
        }

        if ($attempt->started_at !== null) {
            $attempt->elapsed_time += $attempt->started_at->diffInSeconds(now());
            $attempt->started_at = null;
        }

        $attempt->save();

        return response()->json(['success' => true,]);
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

    public function listBestAttempts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'crossword_id' => 'required|integer|exists:crosswords,id',
        ]);

        $attempts = CrosswordAttempt::query()
            ->where('crossword_id', $validated['crossword_id'])
            ->where('status', 'completed')
            ->orderBy('elapsed_time', 'asc')
            ->limit(5)
            ->get();
        
        $table = [];

        foreach ($attempts as $attempt) {
            $user = $attempt->user;

            $table[] = [
                'username' => $user->username,
                'elapsed_time' => $attempt->elapsed_time,
                'completed_at' => $attempt->completed_at,
            ];
        }

        return response()->json([
            'success' => true,
            'table' => $table
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

        $submittedInputs = $validated['word_inputs'];

        $attempt = $this->attemptService->updateAttemptState($attempt, $submittedInputs);
        
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
                'correct_words' => $attempt->grid_state['correct_words'] ?? [],
            ],
            'best_time' => $bestTime ?? null,
        ]);
    }

    public function saveAndStopBeacon(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'attempt_id' => 'required|integer|exists:crossword_attempts,id',
            'state_version' => 'required|integer',
            'word_inputs' => 'required|array',
            'word_inputs.*' => 'required|array',
            'word_inputs.*.*' => 'nullable|string|max:1'
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json(['success' => false], 401);
        }

        $attempt = CrosswordAttempt::query()
            ->whereKey($validated['attempt_id'])
            ->where('user_id', $user->id)
            ->firstOrFail();

        if (!$attempt || $attempt->status !== 'in_progress') {
            return response()->json(['success' => false], 409);
        }

        $submittedInputs = $validated['word_inputs'];

        $attempt = $this->attemptService->updateAttemptState($attempt, $submittedInputs);

        // Ide kell, az updateAttemptState ezt csak akkor végzi el, ha elkészült a rejtvény, de itt minden esetben le kell állítani a próbálkozást.
        if ($attempt->started_at !== null) {
            $attempt->elapsed_time += $attempt->started_at->diffInSeconds(now());
            $attempt->started_at = null;
        }

        $attempt->save();

        return response()->json(['success' => true,]);
    }
}