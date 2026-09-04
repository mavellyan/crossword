<?php

namespace App\Services;

use App\Models\Clue;
use App\Models\Crossword;
use App\Models\CrosswordClue;
use App\Models\CrosswordAttempt;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Collection;

class AttemptService
{
    public function getById(int $crosswordId, ?int $userId = null): array
    {
        if ($userId !== null) {
            $attempt = CrosswordAttempt::query()
                ->where('user_id', $userId)
                ->where('crossword_id', $crosswordId)
                ->latest('id')
                ->first();
        
            if (!$attempt || $attempt->status === 'abandoned') {
                $attempt = CrosswordAttempt::create([
                    'user_id' => $userId,
                    'crossword_id' => $crosswordId,
                    'status' => 'not_started',
                    'grid_state' => [
                        'word_inputs' => [],
                        'correct_words' => [],
                    ],
                    'state_version' => 0,
                    'elapsed_time' => 0,
                ]);
            }

            $bestAttempt = CrosswordAttempt::query()
                ->where('user_id', $userId)
                ->where('crossword_id', $crosswordId)
                ->where('status', 'completed')
                ->orderBy('elapsed_time', 'asc')
                ->first();

            $bestTime = $bestAttempt ? $bestAttempt->elapsed_time : null;
        }

        return [
            'attempt' => $attempt ?? null,
            'best_time' => $bestTime ?? null,
        ];
    }

    public function checkSubmittedInputs(Collection $placements, array $submittedInputs)
    {
        $normalizedInputs = [];
        $isCompleted = true;
        $correctWords = [];

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
            } else {
                $correctWords[] = $placementId;
            }
        }

        return [
            'normalizedInputs' => $normalizedInputs,
            'isCompleted' => $isCompleted,
            'correctWords' => $correctWords,
        ];
    }

    public function updateAttemptState(CrosswordAttempt $attempt, array $submittedInputs): CrosswordAttempt
    {
        
        $placements = $attempt->crossword->getWords()->values();

        $inputCheck = $this->checkSubmittedInputs($placements, $submittedInputs);

        $normalizedInputs = $inputCheck['normalizedInputs'];
        $isCompleted = $inputCheck['isCompleted'];
        $correctWords = $inputCheck['correctWords'];

        $attempt->grid_state = [
            'word_inputs' => $normalizedInputs,
            'correct_words' => $correctWords,
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

        return $attempt;
    }
}