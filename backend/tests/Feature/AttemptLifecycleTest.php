<?php

namespace Tests\Feature;

use App\Enums\Direction;
use App\Models\Clue;
use App\Models\Crossword;
use App\Models\CrosswordAttempt;
use App\Models\CrosswordClue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttemptLifecycleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Létrehoz egy nyilvános rejtvényt két egymást metsző szóval.
     *
     * @return array{user: User, crossword: Crossword, horizontal: CrosswordClue, vertical: CrosswordClue}
     */
    private function createPlayableCrossword(): array
    {
        $creator = User::factory()->create();
        $user = User::factory()->create();

        $crossword = Crossword::factory()->create([
            'user_id' => $creator->id,
            'main_solution' => null,
            'is_public' => true,
        ]);

        $horizontalClue = Clue::factory()->create([
            'definition' => 'Gyümölcs',
            'solution' => 'ALMA',
        ]);

        $verticalClue = Clue::factory()->create([
            'definition' => 'Elv',
            'solution' => 'ELV',
        ]);

        $horizontal = $crossword->crosswordClues()->create([
            'clue_id' => $horizontalClue->id,
            'direction' => Direction::HORIZONTAL,
            'start_row' => 2,
            'start_col' => 1,
        ]);

        $vertical = $crossword->crosswordClues()->create([
            'clue_id' => $verticalClue->id,
            'direction' => Direction::VERTICAL,
            'start_row' => 1,
            'start_col' => 2,
        ]);

        return compact('user', 'crossword', 'horizontal', 'vertical');
    }

    /**
     * Ellenőrzi, hogy az első betöltés létrehoz egy új not_started próbálkozást.
     *
     * @test
     */
    public function testLoadingCrosswordCreatesNotStartedAttempt(): void
    {
        $fixture = $this->createPlayableCrossword();

        $response = $this
            ->actingAs($fixture['user'])
            ->getJson('/api/getAttempt?crossword_id=' . $fixture['crossword']->id);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('attempt.status', 'not_started')
            ->assertJsonPath('attempt.state_version', 0)
            ->assertJsonPath('attempt.elapsed_time', 0)
            ->assertJsonPath('best_time', null);

        $this->assertDatabaseHas('crossword_attempts', [
            'id' => $response->json('attempt.id'),
            'user_id' => $fixture['user']->id,
            'crossword_id' => $fixture['crossword']->id,
            'status' => 'not_started',
        ]);
    }

    /**
     * Ellenőrzi, hogy az ismételt betöltés ugyanazt az aktív próbálkozást adja vissza.
     *
     * @test
     */
    public function testRepeatedLoadingReturnsTheSameAttempt(): void
    {
        $fixture = $this->createPlayableCrossword();

        $first = $this
            ->actingAs($fixture['user'])
            ->getJson('/api/getAttempt?crossword_id=' . $fixture['crossword']->id)
            ->assertOk();

        $second = $this
            ->actingAs($fixture['user'])
            ->getJson('/api/getAttempt?crossword_id=' . $fixture['crossword']->id)
            ->assertOk();

        $this->assertSame($first->json('attempt.id'), $second->json('attempt.id'));
        $this->assertSame(1, CrosswordAttempt::query()
            ->where('user_id', $fixture['user']->id)
            ->where('crossword_id', $fixture['crossword']->id)
            ->count());
    }

    /**
     * Ellenőrzi a betöltés, indítás, mentés, leállítás és újraindítás teljes folyamatát.
     *
     * @test
     */
    public function testAttemptCanBeLoadedStartedSavedStoppedAndRestarted(): void
    {
        $fixedNow = Carbon::parse('2026-01-10 12:00:00');
        $this->travelTo($fixedNow);

        $fixture = $this->createPlayableCrossword();

        $loadResponse = $this
            ->actingAs($fixture['user'])
            ->getJson('/api/getAttempt?crossword_id=' . $fixture['crossword']->id)
            ->assertOk();

        $attemptId = $loadResponse->json('attempt.id');

        $this
            ->actingAs($fixture['user'])
            ->postJson('/api/startAttempt', ['attempt_id' => $attemptId])
            ->assertOk()
            ->assertJsonPath('attempt.status', 'in_progress');

        $this
            ->actingAs($fixture['user'])
            ->postJson('/api/saveProgress', [
                'attempt_id' => $attemptId,
                'state_version' => 0,
                'cell_inputs' => [
                    '2:1' => 'a',
                    '2:2' => 'l',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('save_status', 'saved')
            ->assertJsonPath('attempt.state_version', 1);

        $this->travelTo($fixedNow->copy()->addSeconds(20));

        $this
            ->actingAs($fixture['user'])
            ->postJson('/api/stopAttempt', ['attempt_id' => $attemptId])
            ->assertOk()
            ->assertJsonPath('attempt.elapsed_time', 20)
            ->assertJsonPath('attempt.started_at', null);

        $this
            ->actingAs($fixture['user'])
            ->postJson('/api/startAttempt', ['attempt_id' => $attemptId])
            ->assertOk()
            ->assertJsonPath('attempt.status', 'in_progress');

        $this->travelTo($fixedNow->copy()->addSeconds(35));

        $this
            ->actingAs($fixture['user'])
            ->postJson('/api/stopAttempt', ['attempt_id' => $attemptId])
            ->assertOk()
            ->assertJsonPath('attempt.elapsed_time', 35);

        $attempt = CrosswordAttempt::query()->findOrFail($attemptId);

        $this->assertSame(1, $attempt->state_version);
        $this->assertSame('A', $attempt->grid_state['cell_inputs']['2:1']);
        $this->assertSame('L', $attempt->grid_state['cell_inputs']['2:2']);
        $this->assertNull($attempt->started_at);
    }

    /**
     * Ellenőrzi, hogy az eldobott próbálkozás után a következő betöltés új próbálkozást hoz létre.
     *
     * @test
     */
    public function testAbandoningAttemptAndLoadingAgainCreatesFreshAttempt(): void
    {
        $fixture = $this->createPlayableCrossword();

        $loadResponse = $this
            ->actingAs($fixture['user'])
            ->getJson('/api/getAttempt?crossword_id=' . $fixture['crossword']->id)
            ->assertOk();

        $oldAttemptId = $loadResponse->json('attempt.id');

        $this
            ->actingAs($fixture['user'])
            ->postJson('/api/startAttempt', ['attempt_id' => $oldAttemptId])
            ->assertOk();

        $this
            ->actingAs($fixture['user'])
            ->postJson('/api/abandonAttempt', ['attempt_id' => $oldAttemptId])
            ->assertOk();

        $newAttemptResponse = $this
            ->actingAs($fixture['user'])
            ->getJson('/api/getAttempt?crossword_id=' . $fixture['crossword']->id)
            ->assertOk()
            ->assertJsonPath('attempt.status', 'not_started')
            ->assertJsonPath('attempt.state_version', 0)
            ->assertJsonPath('attempt.elapsed_time', 0);

        $this->assertNotSame($oldAttemptId, $newAttemptResponse->json('attempt.id'));
        $this->assertDatabaseHas('crossword_attempts', [
            'id' => $oldAttemptId,
            'status' => 'abandoned',
        ]);
    }

    /**
     * Ellenőrzi, hogy befejezett próbálkozás eldobásakor azonnal új próbálkozás készül.
     *
     * @test
     */
    public function testAbandoningCompletedAttemptCreatesFreshAttempt(): void
    {
        $fixture = $this->createPlayableCrossword();

        $completedAttempt = CrosswordAttempt::query()->create([
            'user_id' => $fixture['user']->id,
            'crossword_id' => $fixture['crossword']->id,
            'status' => 'completed',
            'grid_state' => [
                'schema_version' => 2,
                'cell_inputs' => [],
                'correct_entry_ids' => [
                    $fixture['horizontal']->id,
                    $fixture['vertical']->id,
                ],
            ],
            'state_version' => 1,
            'elapsed_time' => 50,
            'completed_at' => now(),
        ]);

        $this
            ->actingAs($fixture['user'])
            ->postJson('/api/abandonAttempt', ['attempt_id' => $completedAttempt->id])
            ->assertOk();

        $newAttempt = CrosswordAttempt::query()
            ->where('user_id', $fixture['user']->id)
            ->where('crossword_id', $fixture['crossword']->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertNotSame($completedAttempt->id, $newAttempt->id);
        $this->assertSame('not_started', $newAttempt->status);
        $this->assertSame(0, $newAttempt->state_version);
        $this->assertSame(0, $newAttempt->elapsed_time);

        $completedAttempt->refresh();

        $this->assertSame('completed', $completedAttempt->status);
        $this->assertNull($completedAttempt->abandoned_at);
    }

    /**
     * Ellenőrzi, hogy az aktuális verziójú beacon menti az adatokat és leállítja az időmérőt.
     *
     * @test
     */
    public function testCurrentVersionBeaconSavesStateAndStopsTimer(): void
    {
        $fixedNow = Carbon::parse('2026-01-10 12:00:00');
        $this->travelTo($fixedNow);

        $fixture = $this->createPlayableCrossword();

        $attempt = CrosswordAttempt::query()->create([
            'user_id' => $fixture['user']->id,
            'crossword_id' => $fixture['crossword']->id,
            'status' => 'in_progress',
            'grid_state' => [
                'schema_version' => 2,
                'cell_inputs' => [],
                'correct_entry_ids' => [],
            ],
            'state_version' => 2,
            'elapsed_time' => 5,
            'started_at' => now()->copy()->subSeconds(30),
        ]);

        $this
            ->actingAs($fixture['user'])
            ->postJson('/api/saveAndStopBeacon', [
                'attempt_id' => $attempt->id,
                'state_version' => 2,
                'cell_inputs' => [
                    '2:1' => 'a',
                    '2:2' => 'l',
                    '2:3' => 'x',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $attempt->refresh();

        $this->assertSame(3, $attempt->state_version);
        $this->assertSame(35, $attempt->elapsed_time);
        $this->assertNull($attempt->started_at);
        $this->assertSame('A', $attempt->grid_state['cell_inputs']['2:1']);
        $this->assertSame('L', $attempt->grid_state['cell_inputs']['2:2']);
        $this->assertSame('X', $attempt->grid_state['cell_inputs']['2:3']);
    }

    /**
     * Ellenőrzi a befejezést, az időmérő leállítását és az új legjobb idő visszaadását.
     *
     * @test
     */
    public function testCompletionStoresAndReturnsNewBestTime(): void
    {
        $fixedNow = Carbon::parse('2026-01-10 12:00:00');
        $this->travelTo($fixedNow);

        $fixture = $this->createPlayableCrossword();

        CrosswordAttempt::query()->create([
            'user_id' => $fixture['user']->id,
            'crossword_id' => $fixture['crossword']->id,
            'status' => 'completed',
            'grid_state' => [
                'schema_version' => 2,
                'cell_inputs' => [],
                'correct_entry_ids' => [
                    $fixture['horizontal']->id,
                    $fixture['vertical']->id,
                ],
            ],
            'state_version' => 1,
            'elapsed_time' => 50,
            'completed_at' => now()->copy()->subDay(),
        ]);

        $attempt = CrosswordAttempt::query()->create([
            'user_id' => $fixture['user']->id,
            'crossword_id' => $fixture['crossword']->id,
            'status' => 'in_progress',
            'grid_state' => [
                'schema_version' => 2,
                'cell_inputs' => [],
                'correct_entry_ids' => [],
            ],
            'state_version' => 0,
            'elapsed_time' => 30,
            'started_at' => now()->copy()->subSeconds(10),
        ]);

        $response = $this
            ->actingAs($fixture['user'])
            ->postJson('/api/saveProgress', [
                'attempt_id' => $attempt->id,
                'state_version' => 0,
                'cell_inputs' => [
                    '2:1' => 'A',
                    '2:2' => 'L',
                    '2:3' => 'M',
                    '2:4' => 'A',
                    '1:2' => 'E',
                    '3:2' => 'V',
                ],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('attempt.status', 'completed')
            ->assertJsonPath('attempt.elapsed_time', 40)
            ->assertJsonPath('attempt.started_at', null)
            ->assertJsonPath('best_time', 40);

        $attempt->refresh();

        $this->assertSame('completed', $attempt->status);
        $this->assertSame(40, $attempt->elapsed_time);
        $this->assertNull($attempt->started_at);
        $this->assertNotNull($attempt->completed_at);
    }
}
