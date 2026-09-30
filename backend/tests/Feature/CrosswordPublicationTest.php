<?php

namespace Tests\Feature;

use App\Enums\Direction;
use App\Models\Clue;
use App\Models\Crossword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrosswordPublicationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Clue $hello;
    private Clue $world;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->hello = Clue::factory()->create([
            'definition' => 'Angol köszönés',
            'solution' => 'HELLO',
        ]);
        $this->world = Clue::factory()->create([
            'definition' => 'Világ angolul',
            'solution' => 'WORLD',
        ]);
    }

    /**
     * Létrehoz egy érvényes, két egymást metsző szóból álló rejtvényt.
     */
    private function createValidCrossword(bool $isPublic = false): Crossword
    {
        $crossword = Crossword::factory()->create([
            'user_id' => $this->owner->id,
            'title' => 'Publikálható rejtvény',
            'main_solution' => null,
            'is_public' => $isPublic,
        ]);

        $crossword->crosswordClues()->create([
            'clue_id' => $this->hello->id,
            'direction' => Direction::HORIZONTAL,
            'start_row' => 1,
            'start_col' => 0,
            'is_main' => false,
        ]);

        $crossword->crosswordClues()->create([
            'clue_id' => $this->world->id,
            'direction' => Direction::VERTICAL,
            'start_row' => 0,
            'start_col' => 4,
            'is_main' => false,
        ]);

        return $crossword;
    }

    /**
     * Ellenőrzi, hogy a tulajdonos publikálhat egy érvényes privát vázlatot.
     *
     * @test
     */
    public function testOwnerCanPublishAValidDraft(): void
    {
        $crossword = $this->createValidCrossword();

        $response = $this
            ->actingAs($this->owner)
            ->patchJson('/api/setVisibility', [
                'id' => $crossword->id,
                'is_public' => true,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('is_public', true);

        $this->assertDatabaseHas('crosswords', [
            'id' => $crossword->id,
            'is_public' => true,
        ]);
    }

    /**
     * Ellenőrzi, hogy az érvénytelen elrendezés 422-es választ ad, és privát marad.
     *
     * @test
     */
    public function testInvalidLayoutReturns422AndRemainsPrivate(): void
    {
        $crossword = Crossword::factory()->create([
            'user_id' => $this->owner->id,
            'title' => 'Érvénytelen rejtvény',
            'main_solution' => null,
            'is_public' => false,
        ]);

        $crossword->crosswordClues()->create([
            'clue_id' => $this->hello->id,
            'direction' => Direction::HORIZONTAL,
            'start_row' => 1,
            'start_col' => 0,
            'is_main' => false,
        ]);

        $response = $this
            ->actingAs($this->owner)
            ->patchJson('/api/setVisibility', [
                'id' => $crossword->id,
                'is_public' => true,
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.0.code', 'too_few_entries');

        $this->assertDatabaseHas('crosswords', [
            'id' => $crossword->id,
            'is_public' => false,
        ]);
    }

    /**
     * Ellenőrzi, hogy más felhasználó nem módosíthatja a rejtvény láthatóságát.
     *
     * @test
     */
    public function testNonOwnerCannotChangeVisibility(): void
    {
        $crossword = $this->createValidCrossword();
        $otherUser = User::factory()->create();

        $this
            ->actingAs($otherUser)
            ->patchJson('/api/setVisibility', [
                'id' => $crossword->id,
                'is_public' => true,
            ])
            ->assertForbidden()
            ->assertJsonPath('success', false);

        $this->assertFalse($crossword->fresh()->is_public);
    }

    /**
     * Ellenőrzi, hogy valódi próbálkozás után a rejtvény nem tehető újra priváttá.
     *
     * @test
     */
    public function testRealAttemptPreventsUnpublishing(): void
    {
        $crossword = $this->createValidCrossword(isPublic: true);
        $solver = User::factory()->create();

        $crossword->attempts()->create([
            'user_id' => $solver->id,
            'status' => 'in_progress',
            'grid_state' => [
                'schema_version' => 2,
                'cell_inputs' => [],
                'correct_entry_ids' => [],
            ],
            'state_version' => 0,
            'elapsed_time' => 0,
        ]);

        $this
            ->actingAs($this->owner)
            ->patchJson('/api/setVisibility', [
                'id' => $crossword->id,
                'is_public' => false,
            ])
            ->assertConflict()
            ->assertJsonPath('success', false);

        $this->assertTrue($crossword->fresh()->is_public);
    }

    /**
     * Ellenőrzi, hogy a not_started próbálkozás nem akadályozza a priváttá tételt.
     *
     * @test
     */
    public function testNotStartedAttemptDoesNotPreventUnpublishing(): void
    {
        $crossword = $this->createValidCrossword(isPublic: true);
        $solver = User::factory()->create();

        $crossword->attempts()->create([
            'user_id' => $solver->id,
            'status' => 'not_started',
            'grid_state' => [
                'schema_version' => 2,
                'cell_inputs' => [],
                'correct_entry_ids' => [],
            ],
            'state_version' => 0,
            'elapsed_time' => 0,
        ]);

        $this
            ->actingAs($this->owner)
            ->patchJson('/api/setVisibility', [
                'id' => $crossword->id,
                'is_public' => false,
            ])
            ->assertOk()
            ->assertJsonPath('is_public', false);

        $this->assertFalse($crossword->fresh()->is_public);
    }

    /**
     * Ellenőrzi, hogy nyilvános rejtvény nem szerkeszthető és nem törölhető.
     *
     * @test
     */
    public function testPublicCrosswordCannotBeEditedOrDeleted(): void
    {
        $crossword = $this->createValidCrossword(isPublic: true);

        $updatePayload = [
            'id' => $crossword->id,
            'title' => 'Módosított rejtvény',
            'entries' => [
                [
                    'clue_id' => $this->hello->id,
                    'direction' => Direction::HORIZONTAL,
                    'start_row' => 1,
                    'start_col' => 0,
                ],
                [
                    'clue_id' => $this->world->id,
                    'direction' => Direction::VERTICAL,
                    'start_row' => 0,
                    'start_col' => 4,
                ],
            ],
        ];

        $this
            ->actingAs($this->owner)
            ->putJson('/api/updateCrossword', $updatePayload)
            ->assertConflict();

        $this
            ->actingAs($this->owner)
            ->deleteJson('/api/deleteCrossword', ['id' => $crossword->id])
            ->assertConflict();

        $this->assertDatabaseHas('crosswords', [
            'id' => $crossword->id,
            'deleted_at' => null,
        ]);
    }

    /**
     * Ellenőrzi, hogy valódi próbálkozással rendelkező privát rejtvény sem szerkeszthető vagy törölhető.
     *
     * @test
     */
    public function testAttemptedCrosswordCannotBeEditedOrDeleted(): void
    {
        $crossword = $this->createValidCrossword();
        $solver = User::factory()->create();

        $crossword->attempts()->create([
            'user_id' => $solver->id,
            'status' => 'completed',
            'grid_state' => [
                'schema_version' => 2,
                'cell_inputs' => [],
                'correct_entry_ids' => [],
            ],
            'state_version' => 1,
            'elapsed_time' => 42,
            'completed_at' => now(),
        ]);

        $updatePayload = [
            'id' => $crossword->id,
            'title' => 'Módosított rejtvény',
            'entries' => [
                [
                    'clue_id' => $this->hello->id,
                    'direction' => Direction::HORIZONTAL,
                    'start_row' => 1,
                    'start_col' => 0,
                ],
                [
                    'clue_id' => $this->world->id,
                    'direction' => Direction::VERTICAL,
                    'start_row' => 0,
                    'start_col' => 4,
                ],
            ],
        ];

        $this
            ->actingAs($this->owner)
            ->putJson('/api/updateCrossword', $updatePayload)
            ->assertConflict();

        $this
            ->actingAs($this->owner)
            ->deleteJson('/api/deleteCrossword', ['id' => $crossword->id])
            ->assertConflict();

        $this->assertDatabaseHas('crosswords', [
            'id' => $crossword->id,
            'deleted_at' => null,
        ]);
    }
}
