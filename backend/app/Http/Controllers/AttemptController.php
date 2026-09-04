<?php

namespace App\Http\Controllers;

use App\Models\CrosswordAttempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttemptController extends Controller
{
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
}