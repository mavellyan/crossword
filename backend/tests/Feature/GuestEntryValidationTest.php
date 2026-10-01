<?php

namespace Tests\Feature;

use App\Enums\Direction;
use App\Models\Clue;
use App\Models\Crossword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestEntryValidationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Létrehoz egy nyilvános rejtvényt egy validálható bejegyzéssel.
     */
    private function createPublicCrossword(string $solution = 'ALMA'): array
    {
        $owner = User::factory()->create();
        $crossword = Crossword::factory()->create([
            'user_id' => $owner->id,
            'main_solution' => null,
            'is_public' => true,
        ]);
        $clue = Clue::factory()->create([
            'definition' => 'Teszt meghatározás',
            'solution' => $solution,
        ]);
        $placement = $crossword->crosswordClues()->create([
            'clue_id' => $clue->id,
            'direction' => Direction::HORIZONTAL,
            'start_row' => 0,
            'start_col' => 0,
        ]);

        return compact('crossword', 'placement');
    }

    /**
     * Ellenőrzi, hogy a helyes vendégmegoldás igaz eredményt ad.
     *
     * @test
     */
    public function testCorrectGuestAnswerReturnsTrue(): void
    {
        $fixture = $this->createPublicCrossword('ALMA');

        $this
            ->postJson('/api/validateEntry', [
                'crossword_id' => $fixture['crossword']->id,
                'placement_id' => $fixture['placement']->id,
                'user_input' => 'ALMA',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('is_correct', true);
    }

    /**
     * Ellenőrzi, hogy a helytelen vendégmegoldás hamis eredményt ad.
     *
     * @test
     */
    public function testIncorrectGuestAnswerReturnsFalse(): void
    {
        $fixture = $this->createPublicCrossword('ALMA');

        $this
            ->postJson('/api/validateEntry', [
                'crossword_id' => $fixture['crossword']->id,
                'placement_id' => $fixture['placement']->id,
                'user_input' => 'KÖRTE',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('is_correct', false);
    }

    /**
     * Ellenőrzi, hogy a kisbetűs, ékezetes megoldás is helyesnek számít.
     *
     * @test
     */
    public function testGuestValidationIsCaseInsensitiveAndSupportsAccents(): void
    {
        $fixture = $this->createPublicCrossword('ÁRVÍZ');

        $this
            ->postJson('/api/validateEntry', [
                'crossword_id' => $fixture['crossword']->id,
                'placement_id' => $fixture['placement']->id,
                'user_input' => 'árvíz',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('is_correct', true);
    }

    /**
     * Ellenőrzi, hogy másik rejtvényhez tartozó placement azonosítója elutasításra kerül.
     *
     * @test
     */
    public function testPlacementFromAnotherCrosswordIsRejected(): void
    {
        $first = $this->createPublicCrossword('ALMA');
        $second = $this->createPublicCrossword('KÖRTE');

        $this
            ->postJson('/api/validateEntry', [
                'crossword_id' => $first['crossword']->id,
                'placement_id' => $second['placement']->id,
                'user_input' => 'KÖRTE',
            ])
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    /**
     * Ellenőrzi, hogy a nem létező placement azonosítója 404-es hibát ad.
     *
     * @test
     */
    public function testNonexistentPlacementIsRejected(): void
    {
        $fixture = $this->createPublicCrossword('ALMA');

        $this
            ->postJson('/api/validateEntry', [
                'crossword_id' => $fixture['crossword']->id,
                'placement_id' => 999999,
                'user_input' => 'ALMA',
            ])
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }
}
