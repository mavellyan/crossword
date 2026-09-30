<?php

namespace Tests\Feature;

use App\Enums\Direction;
use App\Models\Clue;
use App\Models\Crossword;
use App\Models\CrosswordClue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrosswordCoordinateNormalizationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Clue $hello;
    private Clue $world;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

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
     * Visszaadja az eltolt, de érvényes elrendezést.
     */
    private function offsetEntries(): array
    {
        return [
            [
                'clue_id' => $this->hello->id,
                'direction' => Direction::HORIZONTAL,
                'start_row' => 4,
                'start_col' => 5,
            ],
            [
                'clue_id' => $this->world->id,
                'direction' => Direction::VERTICAL,
                'start_row' => 3,
                'start_col' => 9,
            ],
        ];
    }

    /**
     * Visszaadja az elvárt normalizált elrendezést.
     */
    private function normalizedEntries(): array
    {
        return [
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
        ];
    }

    /**
     * Létrehoz egy rejtvényt az API-n keresztül.
     */
    private function createCrossword(array $entries): Crossword
    {
        $response = $this
            ->actingAs($this->user)
            ->postJson('/api/createCrossword', [
                'title' => 'Normalizált rejtvény',
                'entries' => $entries,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true);

        return Crossword::query()->findOrFail(
            $response->json('crossword.id')
        );
    }

    /**
     * Ellenőrzi, hogy létrehozáskor az eltolt koordináták nullához igazodnak.
     *
     * @test
     */
    public function testCreatingOffsetLayoutNormalizesStoredCoordinates(): void
    {
        $crossword = $this->createCrossword($this->offsetEntries());

        $helloEntry = $crossword->crosswordClues()
            ->where('clue_id', $this->hello->id)
            ->firstOrFail();

        $worldEntry = $crossword->crosswordClues()
            ->where('clue_id', $this->world->id)
            ->firstOrFail();

        $this->assertSame(1, $helloEntry->start_row);
        $this->assertSame(0, $helloEntry->start_col);
        $this->assertSame(Direction::HORIZONTAL->value, $helloEntry->getDirection());

        $this->assertSame(0, $worldEntry->start_row);
        $this->assertSame(4, $worldEntry->start_col);
        $this->assertSame(Direction::VERTICAL->value, $worldEntry->getDirection());

        $this->assertSame(2, $crossword->crosswordClues()->count());
    }

    /**
     * Ellenőrzi, hogy a normalizálás után a két szó metszéspontja változatlanul érvényes.
     *
     * @test
     */
    public function testIntersectionRemainsValidAfterNormalization(): void
    {
        $crossword = $this->createCrossword($this->offsetEntries());

        $helloEntry = $crossword->crosswordClues()
            ->where('clue_id', $this->hello->id)
            ->firstOrFail();

        $worldEntry = $crossword->crosswordClues()
            ->where('clue_id', $this->world->id)
            ->firstOrFail();

        $helloIntersection = collect($helloEntry->getCells())
            ->first(fn (array $cell) =>
                $cell['row'] === 1 && $cell['col'] === 4
            );

        $worldIntersection = collect($worldEntry->getCells())
            ->first(fn (array $cell) =>
                $cell['row'] === 1 && $cell['col'] === 4
            );

        $this->assertNotNull($helloIntersection);
        $this->assertNotNull($worldIntersection);
        $this->assertSame('O', $helloIntersection['letter']);
        $this->assertSame('O', $worldIntersection['letter']);
    }

    /**
     * Ellenőrzi, hogy a solver normalizált koordinátákat és levágott rácsot kap.
     *
     * @test
     */
    public function testSolverResponseUsesNormalizedCoordinatesAndTrimmedGrid(): void
    {
        $crossword = $this->createCrossword($this->offsetEntries());

        $this
            ->actingAs($this->user)
            ->patchJson('/api/setVisibility', [
                'id' => $crossword->id,
                'is_public' => true,
            ])
            ->assertOk()
            ->assertJsonPath('is_public', true);

        $response = $this
            ->getJson('/api/getCrossword?id=' . $crossword->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('crossword.width', 5)
            ->assertJsonPath('crossword.height', 5);

        $words = collect($response->json('crossword.words'));

        $helloEntry = $crossword->crosswordClues()
            ->where('clue_id', $this->hello->id)
            ->firstOrFail();

        $worldEntry = $crossword->crosswordClues()
            ->where('clue_id', $this->world->id)
            ->firstOrFail();

        $helloWord = $words->firstWhere('placement_id', $helloEntry->id);
        $worldWord = $words->firstWhere('placement_id', $worldEntry->id);

        $this->assertSame(1, $helloWord['start_row']);
        $this->assertSame(0, $helloWord['start_col']);
        $this->assertSame('horizontal', $helloWord['direction']);

        $this->assertSame(0, $worldWord['start_row']);
        $this->assertSame(4, $worldWord['start_col']);
        $this->assertSame('vertical', $worldWord['direction']);

        $grid = $response->json('crossword.grid');

        $this->assertCount(5, $grid);
        $this->assertCount(5, $grid[0]);

        // Az első sorban a WORLD első cellája található.
        $this->assertTrue(in_array(null, $grid[0], true));

        // Az első oszlopban a HELLO első cellája található.
        $firstColumn = array_column($grid, 0);
        $this->assertTrue(in_array(null, $firstColumn, true));

        // A normalizált metszéspont a solver rácsában is játszható cella.
        $this->assertNull($grid[1][4]);
    }

    /**
     * Ellenőrzi, hogy frissítéskor is normalizálódnak az eltolt koordináták.
     *
     * @test
     */
    public function testUpdatingOffsetLayoutNormalizesStoredCoordinates(): void
    {
        $crossword = $this->createCrossword($this->normalizedEntries());

        $response = $this
            ->actingAs($this->user)
            ->putJson('/api/updateCrossword', [
                'id' => $crossword->id,
                'title' => 'Frissített normalizált rejtvény',
                'entries' => $this->offsetEntries(),
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true);

        $helloEntry = CrosswordClue::query()
            ->where('crossword_id', $crossword->id)
            ->where('clue_id', $this->hello->id)
            ->firstOrFail();

        $worldEntry = CrosswordClue::query()
            ->where('crossword_id', $crossword->id)
            ->where('clue_id', $this->world->id)
            ->firstOrFail();

        $this->assertSame(1, $helloEntry->start_row);
        $this->assertSame(0, $helloEntry->start_col);
        $this->assertSame(0, $worldEntry->start_row);
        $this->assertSame(4, $worldEntry->start_col);

        $this->assertSame(
            2,
            CrosswordClue::query()
                ->where('crossword_id', $crossword->id)
                ->count(),
        );

        $helloIntersection = collect($helloEntry->getCells())
            ->first(fn (array $cell) =>
                $cell['row'] === 1 && $cell['col'] === 4
            );

        $worldIntersection = collect($worldEntry->getCells())
            ->first(fn (array $cell) =>
                $cell['row'] === 1 && $cell['col'] === 4
            );

        $this->assertSame('O', $helloIntersection['letter']);
        $this->assertSame('O', $worldIntersection['letter']);
    }
}