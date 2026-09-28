<?php

namespace App\Services;

use App\Models\Crossword;
use App\Models\CrosswordClue;
use App\Models\CrosswordAttempt;
use Illuminate\Support\Facades\DB;
use App\Exceptions\AttemptStateConflict;
use App\Exceptions\CrosswordUnavailableException;
use Illuminate\Auth\Access\AuthorizationException;

class AttemptService
{
    public function getById(int $crosswordId, ?int $userId = null): array
    {
        if ($userId === null) {
            throw new AuthorizationException('A próbálkozás lekéréséhez be kell jelentkezni.');
        }

        return DB::transaction(function () use ($crosswordId, $userId) {
            $crossword = Crossword::query()
                ->whereKey($crosswordId)
                ->lockForUpdate()
                ->firstOrFail();
            
            if (!$crossword->is_public) {
                throw new CrosswordUnavailableException('Ez a rejtvény nem nyilvános, így nem próbálható ki.');
            }

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
                        'schema_version' => 2,
                        'cell_inputs' => [],
                        'correct_entry_ids' => [],
                    ],
                    'state_version' => 0,
                    'elapsed_time' => 0,
                ]);
            }

            $state = is_array($attempt->grid_state) ? $attempt->grid_state : [];

            $isV2 = ($state['schema_version'] ?? null) === 2 && array_key_exists('cell_inputs', $state);

            if (!$isV2) {
                $attempt = $this->migrateLegacyAttempt($attempt->id);
            }

            $bestTime = CrosswordAttempt::query()
                ->where('user_id', $userId)
                ->where('crossword_id', $crosswordId)
                ->where('status', 'completed')
                ->min('elapsed_time');

            return [
                'attempt' => $attempt,
                'best_time' => $bestTime,
            ];
        });
    }

    public function updateAttemptState(int $attemptId, int $userId, array $submittedInputs, int $expectedVersion, bool $beacon = false): CrosswordAttempt
    {
        return DB::transaction(function () use ($attemptId, $userId, $submittedInputs, $expectedVersion, $beacon) {
            $attempt = CrosswordAttempt::query()->whereKey($attemptId)->where('user_id', $userId)->lockForUpdate()->firstOrFail();

            if ($attempt->status !== 'in_progress') {
                throw new AttemptStateConflict('Ez a próbálkozás nem módosítható!');
            }

            $isStale = (int) $attempt->state_version !== $expectedVersion;

            if ($isStale && !$beacon) {
                throw new AttemptStateConflict('A próbálkozás állapota megváltozott, frissítse az oldalt!');
            }

            // Ha beacon jelzés érkezett, és az állapot elavult, akkor nem frissítjük az állapotot, csak a started_at és elapsed_time mezőket kezeljük.
            // Mivel a többi mező értéke elavult, ezért nem akarjuk felülírni a felhasználó által már beküldött adatokat.
            if ($isStale && $beacon) {
                $this->stopActiveInterval($attempt);

                $attempt->save();
                return $attempt;
            }

            $inputCheck = $this->evaluateEntries($attempt->crossword, $submittedInputs);

            $normalizedInputs = $inputCheck['cell_inputs'];
            $isCompleted = $inputCheck['is_completed'];
            $correctWords = $inputCheck['correct_entry_ids'];

            $attempt->grid_state = [
                'schema_version' => 2,
                'cell_inputs' => $normalizedInputs,
                'correct_entry_ids' => $correctWords,
            ];

            $attempt->state_version++;

            // Ha beacon jelzés érkezett, viszont nem elavult, vagy befejeződött a próbálkozás, akkor csak a mezők frissítése után állítjuk le a timert
            if ($beacon || $isCompleted) {
                $this->stopActiveInterval($attempt);
            }

            if ($isCompleted) {
                $attempt->status = 'completed';
                $attempt->completed_at = now();
            }
            
            $attempt->save();

            return $attempt;
        });
    }

    public function stopActiveInterval(CrosswordAttempt $attempt): void
    {
        if ($attempt->started_at === null) {
            return;
        }

        $elapsedSeconds = (int) floor($attempt->started_at->diffInSeconds(now()));

        $attempt->elapsed_time += $elapsedSeconds;
        $attempt->started_at = null;
    }

    public function startAttempt(int $attemptId, int $userId): CrosswordAttempt
    {
        return DB::transaction(function () use ($attemptId, $userId) {
            $crossword = Crossword::query()
                ->whereHas('attempts', function ($query) use ($attemptId, $userId) {
                    $query->whereKey($attemptId)->where('user_id', $userId);
                })
                ->lockForUpdate()
                ->firstOrFail();

            if (!$crossword->is_public) {
                throw new CrosswordUnavailableException('Ez a rejtvény nem nyilvános, így nem próbálható ki.');
            }

            $attempt = CrosswordAttempt::query()->whereKey($attemptId)->where('user_id', $userId)->lockForUpdate()->firstOrFail();

            if (!in_array($attempt->status, ['not_started', 'in_progress'], true)) {
                throw new AttemptStateConflict('Ez a próbálkozás nem indítható el.');
            }

            if ($attempt->status === 'not_started') {
                $attempt->status = 'in_progress';
            }

            if ($attempt->started_at === null) {
                $attempt->started_at = now();
            }

            $attempt->save();

            return $attempt;
        });
    }

    public function stopAttempt(int $attemptId, int $userId): CrosswordAttempt
    {
        return DB::transaction(function () use ($attemptId, $userId) {
            $attempt = CrosswordAttempt::query()->whereKey($attemptId)->where('user_id', $userId)->lockForUpdate()->firstOrFail();

            if ($attempt->status !== 'in_progress') {
                throw new AttemptStateConflict('Ez a próbálkozás nem állítható le.');
            }

            $this->stopActiveInterval($attempt);

            $attempt->save();

            return $attempt;
        });
    }

    public function abandonAttempt(int $attemptId, int $userId): CrosswordAttempt
    {
        return DB::transaction(function () use ($attemptId, $userId) {
            $attempt = CrosswordAttempt::query()->whereKey($attemptId)->where('user_id', $userId)->lockForUpdate()->firstOrFail();

            if (!in_array($attempt->status, ['in_progress', 'completed'], true)) {
                throw new AttemptStateConflict('Ez a próbálkozás még nincs elkezdve, vagy már el van dobva, nem lehet eldobni.');
            }

            // Ha a próbálkozás már be van fejezve, akkor nem kell semmit csinálni, csak indítunk egy újat.
            if ($attempt->status === 'completed') {
                $newAttempt = CrosswordAttempt::create([
                    'user_id' => $userId,
                    'crossword_id' => $attempt->crossword_id,
                    'status' => 'not_started',
                    'grid_state' => [
                        'schema_version' => 2,
                        'cell_inputs' => [],
                        'correct_entry_ids' => [],
                    ],
                    'state_version' => 0,
                    'elapsed_time' => 0,
                ]);

                $newAttempt->save();

                return $newAttempt;
            }

            $this->stopActiveInterval($attempt);

            $attempt->status = 'abandoned';
            $attempt->abandoned_at = now();
            $attempt->save();

            return $attempt;
        });
    }

    public function listBestAttempts(int $crosswordId): array
    {
        $bestAttempts = CrosswordAttempt::query()
            ->with('user:id,username')
            ->where('crossword_id', $crosswordId)
            ->where('status', 'completed')
            ->orderBy('elapsed_time', 'asc')
            ->limit(5)
            ->get();

        return $bestAttempts->map(function ($attempt) {
            return [
                'username' => $attempt->user?->username,
                'elapsed_time' => $attempt->elapsed_time,
                'completed_at' => $attempt->completed_at,
            ];
        })->toArray();
    }

    /**
     * Normalizálja a megadott cella bemeneteket a keresztrejtvényhez.
     * A bemeneteket nagybetűs formára alakítja, és csak a játszható cellákhoz tartozó kulcsokat tartja meg.
     * 
     * @param Crossword $crossword A keresztrejtvény modell példánya.
     * @param array $inputs A felhasználó által megadott cella bemenetek tömbje, ahol a kulcs a cella sora:oszlopa formátumú.
     * @return array A normalizált cella bemenetek tömbje
     */
    private function normalizeCellInputs(Crossword $crossword, array $inputs): array
    {
        $normalizedInputs = [];

        foreach ($inputs as $cellKey => $value) {
            $normalizedInputs[$cellKey] = mb_strtoupper(mb_substr((string) $value, 0, 1));
        }

        $placements = $crossword->getWords()->values();
        $crosswordCellKeys = [];

        foreach ($placements as $placement) {
            $expectedSolution = mb_strtoupper($placement->getSolution());
            $expectedLength = mb_strlen($expectedSolution);

            for ($index = 0; $index < $expectedLength; $index++) {

                $cellKey = $this->cellKeyForPlacement($placement, $index);
                $crosswordCellKeys[] = $cellKey;

                if (!array_key_exists($cellKey, $normalizedInputs)) {
                    $normalizedInputs[$cellKey] = '';
                }
            }
        }

        foreach ($normalizedInputs as $cellKey => $value) {
            if (!in_array($cellKey, $crosswordCellKeys, true)) {
                unset($normalizedInputs[$cellKey]);
            }
        }

        return $normalizedInputs;
    }

    /**
     * Értékeli a megadott cella bemeneteket a keresztrejtvényhez.
     * 
     * @param Crossword $crossword A keresztrejtvény modell példánya.
     * @param array $cellInputs A cella bemenetek tömbje.
     * @return array A cella bemenetek értékeléséből származó eredmények tömbje.
     */
    private function evaluateEntries(Crossword $crossword, array $cellInputs): array
    {
        $normalizedInputs = $this->normalizeCellInputs($crossword, $cellInputs);
        $placements = $crossword->getWords()->values();
        $correctEntryIds = [];

        foreach ($placements as $placement) {
            $expectedSolution = mb_strtoupper($placement->getSolution());
            $enteredSolution = '';

            for ($index = 0; $index < mb_strlen($expectedSolution); $index++) {
                $cellKey = $this->cellKeyForPlacement($placement, $index);
                $enteredSolution .= $normalizedInputs[$cellKey] ?? '';
            }

            if ($enteredSolution === $expectedSolution) {
                $correctEntryIds[] = $placement->id;
            }
        }

        return [
            'cell_inputs' => $normalizedInputs,
            'correct_entry_ids' => $correctEntryIds,
            'is_completed' => $placements->isNotEmpty() && count($correctEntryIds) === $placements->count()
        ];
    }

    /**
     * Átmeneti állapot migrálása a keresztrejtvény próbához.
     *
     * @param CrosswordAttempt $attempt A keresztrejtvény próbája.
     * @return array Az átmeneti állapot migrálásának eredménye.
     */
    private function migrateLegacyGridState(CrosswordAttempt $attempt): array
    {
        $legacyState = is_array($attempt->grid_state) ? $attempt->grid_state : [];
        $wordInputs = $legacyState['word_inputs'] ?? [];

        $placements = $attempt->crossword->getWords()->values();

        $inputCells = [];

        foreach ($wordInputs as $key => $cells) {
            $placement = $placements->firstWhere('id', $key);

            if (!$placement) {
                continue;
            }

            $expectedSolution = mb_strtoupper($placement->getSolution());

            for ($index = 0; $index < mb_strlen($expectedSolution); $index++) {
                $cellKey = $this->cellKeyForPlacement($placement, $index);

                $incoming = mb_strtoupper(mb_substr((string) ($cells[$index] ?? ''), 0, 1));

                if (!array_key_exists($cellKey, $inputCells)) {
                    $inputCells[$cellKey] = $incoming;
                    continue;
                }

                $existing = $inputCells[$cellKey];

                if ($existing === '') {
                    $inputCells[$cellKey] = $incoming;
                } elseif ($incoming === '' || $existing === $incoming) {
                    // Ha az új beírás üres, vagy megegyezik a meglévővel, akkor nem változtatunk rajta.
                    $inputCells[$cellKey] = $existing;
                } else {
                    // Ha az új beírás nem üres és eltér a meglévőtől, akkor töröljük a meglévőt, hogy ne legyen ellentmondás.
                    $inputCells[$cellKey] = '';
                }
            }
        }

        $evaluationResult = $this->evaluateEntries($attempt->crossword, $inputCells);

        return [
            'schema_version' => 2,
            'cell_inputs' => $evaluationResult['cell_inputs'],
            'correct_entry_ids' => $evaluationResult['correct_entry_ids']
        ];
    }

    /**
     * Átmeneti állapot migrálása a keresztrejtvény próbához.
     *
     * @param int $attemptId A keresztrejtvény próbája azonosítója.
     * @return CrosswordAttempt A migrált keresztrejtvény próbája.
     */
    private function migrateLegacyAttempt(int $attemptId): CrosswordAttempt
    {
        return DB::transaction(function () use ($attemptId) {
            $attempt = CrosswordAttempt::query()->whereKey($attemptId)->lockForUpdate()->firstOrFail();

            $state = is_array($attempt->grid_state) ? $attempt->grid_state : [];

            $isV2 = ($state['schema_version'] ?? null) === 2 && array_key_exists('cell_inputs', $state);

            if (!$isV2) {
                $attempt->grid_state = $this->migrateLegacyGridState($attempt);
                $attempt->save();
            }

            return $attempt;
        });
    }

    /**
     * Visszaadja a cella kulcsát a megadott elhelyezés és index alapján.
     * 
     * @param CrosswordClue $placement A keresztrejtvény elhelyezése.
     * @param int $index A cella indexe az elhelyezésen belül.
     * @return string A cella kulcsa "sor:oszlop" formátumban.
     */
    private function cellKeyForPlacement(CrosswordClue $placement, int $index): string
    {
        $direction = $placement->getDirection();

        $row = $placement->getStartRow() + ($direction === 'vertical' ? $index : 0);
        $col = $placement->getStartCol() + ($direction === 'horizontal' ? $index : 0);

        return "{$row}:{$col}";
    }
}