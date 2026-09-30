<?php

namespace Tests\Feature;

use App\Enums\Direction;
use App\Models\Clue;
use App\Models\Crossword;
use App\Models\CrosswordAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrosswordSecurityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Létrehoz egy rejtvényt egy elhelyezett szóval a biztonsági tesztekhez.
     *
     * @return array{owner: User, crossword: Crossword, clue: Clue, placement_id: int}
     */
    private function createCrossword(bool $isPublic): array
    {
        $owner = User::factory()->create([
            'username' => 'security_owner',
        ]);
        $clue = Clue::factory()->create([
            'definition' => 'Angol köszönés',
            'solution' => 'HELLO',
        ]);

        $crossword = Crossword::factory()->create([
            'user_id' => $owner->id,
            'title' => 'Biztonsági teszt',
            'main_solution' => 'TITOK',
            'is_public' => $isPublic,
        ]);

        $placement = $crossword->crosswordClues()->create([
            'clue_id' => $clue->id,
            'direction' => Direction::HORIZONTAL,
            'start_row' => 1,
            'start_col' => 0,
            'is_main' => false,
        ]);

        return [
            'owner' => $owner,
            'crossword' => $crossword,
            'clue' => $clue,
            'placement_id' => $placement->id,
        ];
    }

    /**
     * Ellenőrzi, hogy a vendégként lekért solver válasz nem tartalmaz megoldásokat.
     *
     * @test
     */
    public function testGuestSolverResponseDoesNotExposeSolutions(): void
    {
        $fixture = $this->createCrossword(isPublic: true);

        $response = $this
            ->getJson('/api/getCrossword?id=' . $fixture['crossword']->id)
            ->assertOk()
            ->assertJsonPath('success', true);

        $crossword = $response->json('crossword');

        $this->assertArrayNotHasKey('main_solution', $crossword);
        $this->assertStringNotContainsString('TITOK', $response->getContent());
        $this->assertStringNotContainsString('HELLO', $response->getContent());

        foreach ($crossword['words'] as $word) {
            $this->assertArrayNotHasKey('solution', $word);
        }

        foreach ($crossword['grid'] as $row) {
            foreach ($row as $cell) {
                $this->assertContains($cell, ['#', null], true);
            }
        }
    }

    /**
     * Ellenőrzi, hogy privát rejtvény nem tölthető be a solver végponton.
     *
     * @test
     */
    public function testPrivateCrosswordCannotBeLoadedForSolving(): void
    {
        $fixture = $this->createCrossword(isPublic: false);

        $this
            ->getJson('/api/getCrossword?id=' . $fixture['crossword']->id)
            ->assertNotFound();

        $otherUser = User::factory()->create();

        $this
            ->actingAs($otherUser)
            ->getJson('/api/getCrossword?id=' . $fixture['crossword']->id)
            ->assertNotFound();
    }

    /**
     * Ellenőrzi, hogy privát rejtvényhez bejelentkezett felhasználó sem hozhat létre próbálkozást.
     *
     * @test
     */
    public function testPrivateCrosswordCannotCreateAttempt(): void
    {
        $fixture = $this->createCrossword(isPublic: false);
        $otherUser = User::factory()->create();

        $this
            ->actingAs($otherUser)
            ->getJson('/api/getAttempt?crossword_id=' . $fixture['crossword']->id)
            ->assertForbidden()
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('crossword_attempts', [
            'user_id' => $otherUser->id,
            'crossword_id' => $fixture['crossword']->id,
        ]);
    }

    /**
     * Ellenőrzi, hogy a vendég validáció nem használható privát rejtvényen.
     *
     * @test
     */
    public function testGuestValidationRejectsPrivateCrossword(): void
    {
        $fixture = $this->createCrossword(isPublic: false);

        $this
            ->postJson('/api/validateEntry', [
                'crossword_id' => $fixture['crossword']->id,
                'placement_id' => $fixture['placement_id'],
                'user_input' => 'HELLO',
            ])
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    /**
     * Ellenőrzi, hogy másik felhasználó nem módosíthatja egy idegen próbálkozás állapotát.
     *
     * @test
     */
    public function testAnotherUserCannotAccessAttemptMutations(): void
    {
        $fixture = $this->createCrossword(isPublic: true);
        $solver = User::factory()->create();
        $attacker = User::factory()->create();

        $attempt = CrosswordAttempt::query()->create([
            'user_id' => $solver->id,
            'crossword_id' => $fixture['crossword']->id,
            'status' => 'in_progress',
            'grid_state' => [
                'schema_version' => 2,
                'cell_inputs' => ['1:0' => 'H'],
                'correct_entry_ids' => [],
            ],
            'state_version' => 3,
            'elapsed_time' => 10,
            'started_at' => now(),
        ]);

        $this
            ->actingAs($attacker)
            ->postJson('/api/startAttempt', ['attempt_id' => $attempt->id])
            ->assertNotFound();

        $this
            ->actingAs($attacker)
            ->postJson('/api/stopAttempt', ['attempt_id' => $attempt->id])
            ->assertNotFound();

        $this
            ->actingAs($attacker)
            ->postJson('/api/saveProgress', [
                'attempt_id' => $attempt->id,
                'state_version' => 3,
                'cell_inputs' => ['1:0' => 'X'],
            ])
            ->assertNotFound()
            ->assertJsonPath('save_status', 'failed');

        $this
            ->actingAs($attacker)
            ->postJson('/api/saveAndStopBeacon', [
                'attempt_id' => $attempt->id,
                'state_version' => 3,
                'cell_inputs' => ['1:0' => 'X'],
            ])
            ->assertNotFound();

        $this
            ->actingAs($attacker)
            ->postJson('/api/abandonAttempt', ['attempt_id' => $attempt->id])
            ->assertNotFound();

        $attempt->refresh();

        $this->assertSame('in_progress', $attempt->status);
        $this->assertSame(3, $attempt->state_version);
        $this->assertSame('H', $attempt->grid_state['cell_inputs']['1:0']);
        $this->assertSame(10, $attempt->elapsed_time);
        $this->assertNotNull($attempt->started_at);
    }
}
