<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Clue;
use App\Models\Crossword;
use App\Models\CrosswordClue;
use App\Models\CrosswordAttempt;
use App\Enums\Direction;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AttemptStateTest extends TestCase
{
    use RefreshDatabase;
    /**
     * Létrehoz egy rögzített próbálkozási állapotot a teszteléshez.
     *
     * @param string $status A próbálkozás állapota.
     * @param array $gridState A keresztrejtvény hálózatának állapota.
     * @return array A létrehozott próbálkozási állapot elemei.
     */
    private function createAttemptFixture(
        string $status = 'in_progress',
        array $gridState = [
            'schema_version' => 2,
            'cell_inputs' => [],
            'correct_entry_ids' => [],
        ],
    ) : array {
        $user = User::factory()->create();

        $crossword = Crossword::factory()->create([
            'user_id' => $user->id,
            'main_solution' => null,
        ]);

        $horizontalClue = Clue::factory()->create([
            'definition' => 'Gyümölcs',
            'solution' => 'ALMA',
        ]);

        $verticalClue = Clue::factory()->create([
            'definition' => 'Elv',
            'solution' => 'ELV',
        ]);

        $horizontal = CrosswordClue::query()->create([
            'crossword_id' => $crossword->id,
            'clue_id' => $horizontalClue->id,
            'direction' => Direction::HORIZONTAL,
            'start_row' => 2,
            'start_col' => 1,
            'is_main' => false,
        ]);

        $vertical = CrosswordClue::query()->create([
            'crossword_id' => $crossword->id,
            'clue_id' => $verticalClue->id,
            'direction' => Direction::VERTICAL,
            'start_row' => 1,
            'start_col' => 2,
            'is_main' => false,
        ]);

        $attempt = CrosswordAttempt::query()->create([
            'crossword_id' => $crossword->id,
            'user_id' => $user->id,
            'status' => $status,
            'grid_state' => $gridState,
            'state_version' => 0,
            'elapsed_time' => 0,
            'started_at' => $status === 'in_progress' ? now() : null,
        ]);

        return compact(
            'user',
            'crossword',
            'horizontal',
            'vertical',
            'attempt',
        );
    }

    /**
     * Ellenőrzi, hogy a teljesen kitöltött bejegyzés helyesnek számít, míg a részleges bejegyzés nem.
     * 
     * @test
     */
    public function testCompleteEntryIsCorrectAndPartialEntryIsNot(): void
    {
        $fixture = $this->createAttemptFixture();

        $response = $this
            ->actingAs($fixture['user'])
            ->postJson('/api/saveProgress', [
                'attempt_id' => $fixture['attempt']->id,
                'state_version' => 0,
                'cell_inputs' => [
                    '2:1' => 'A',
                    '2:2' => 'L',
                    '2:3' => 'M', // Hiányzik az utolsó 'A'
                ],
            ]);

        $response->assertOk();
        $this->assertEmpty($response->json('attempt.correct_entry_ids'), 'Részleges megoldás nem lehet helyes');

        $response2 = $this
            ->actingAs($fixture['user'])
            ->postJson('/api/saveProgress', [
                'attempt_id' => $fixture['attempt']->id,
                'state_version' => 1,
                'cell_inputs' => [
                    '2:1' => 'A',
                    '2:2' => 'L',
                    '2:3' => 'M',
                    '2:4' => 'A', // Teljes
                ],
            ]);

        $response2->assertOk();
        $this->assertContains($fixture['horizontal']->id, $response2->json('attempt.correct_entry_ids'));
    }

    /**
     * Ellenőrzi, hogy két metsző bejegyzés helyesnek számít, ha egy közös koordinátát használnak.
     * 
     * @test
     */
    public function testBothIntersectingEntriesCanBeCorrectUsingOneSharedCoordinate(): void
    {
        $fixture = $this->createAttemptFixture();

        // ALMA (vízszintes 2:1-től) és ELV (függőleges 1:2-től) metszi egymást a 2:2 koordinátán ('L' betű).
        $response = $this
            ->actingAs($fixture['user'])
            ->postJson('/api/saveProgress', [
                'attempt_id' => $fixture['attempt']->id,
                'state_version' => 0,
                'cell_inputs' => [
                    '2:1' => 'A',
                    '2:2' => 'L', // Metszéspont
                    '2:3' => 'M',
                    '2:4' => 'A',
                    '1:2' => 'E',
                    '3:2' => 'V',
                ],
            ]);

        $response->assertOk();
        
        $correctIds = $response->json('attempt.correct_entry_ids');
        $this->assertContains($fixture['horizontal']->id, $correctIds);
        $this->assertContains($fixture['vertical']->id, $correctIds);
        $this->assertCount(2, $correctIds);
    }

    /**
     * Ellenőrzi, hogy a helytelen metszéspont mindkét bejegyzést érvényteleníti.
     * 
     * @test
     */
    public function testWrongIntersectionInvalidatesBothEntries(): void
    {
        $fixture = $this->createAttemptFixture();

        $response = $this
            ->actingAs($fixture['user'])
            ->postJson('/api/saveProgress', [
                'attempt_id' => $fixture['attempt']->id,
                'state_version' => 0,
                'cell_inputs' => [
                    '2:1' => 'A',
                    '2:2' => 'X', // HIBÁS Metszéspont (L helyett X)
                    '2:3' => 'M',
                    '2:4' => 'A',
                    '1:2' => 'E',
                    '3:2' => 'V',
                ],
            ]);

        $response->assertOk();
        
        $correctIds = $response->json('attempt.correct_entry_ids');
        $this->assertEmpty($correctIds, 'Hibás metszéspont miatt egyik bejegyzés sem lehet helyes');
    }

    /**
     * Ellenőrzi, hogy a próbálkozás csak akkor tekinthető befejezettnek, ha minden bejegyzés helyes.
     * Valamint ellenőrzi, hogy a started_at mező nullázódik a befejezéskor és a timer leáll.
     * 
     * @test
     */
    public function testCompletionOnlyWhenAllEntriesAreCorrect(): void
    {
        $fixture = $this->createAttemptFixture();

        // Csak az egyik szó (ALMA) van kész
        $response1 = $this
            ->actingAs($fixture['user'])
            ->postJson('/api/saveProgress', [
                'attempt_id' => $fixture['attempt']->id,
                'state_version' => 0,
                'cell_inputs' => [
                    '2:1' => 'A',
                    '2:2' => 'L',
                    '2:3' => 'M',
                    '2:4' => 'A',
                ],
            ]);

        $response1->assertOk();
        $this->assertEquals('in_progress', $response1->json('attempt.status'));

        // Mindkét szó elkészül
        $response2 = $this
            ->actingAs($fixture['user'])
            ->postJson('/api/saveProgress', [
                'attempt_id' => $fixture['attempt']->id,
                'state_version' => 1,
                'cell_inputs' => [
                    '2:1' => 'A',
                    '2:2' => 'L',
                    '2:3' => 'M',
                    '2:4' => 'A',
                    '1:2' => 'E',
                    '3:2' => 'V',
                ],
            ]);

        $response2->assertOk();
        
        $attempt = CrosswordAttempt::find($fixture['attempt']->id);
        $this->assertEquals('completed', $attempt->status);
        $this->assertNull($attempt->started_at);
        $this->assertNotNull($attempt->completed_at);
    }

    /**
     * Ellenőrzi, hogy a próbálkozás állapotából eltávolításra kerülnek az ismeretlen koordináták.
     * 
     * @test
     */
    public function testUnknownCoordinatesAreRemovedFromStoredState(): void
    {
        $fixture = $this->createAttemptFixture();

        $response = $this
            ->actingAs($fixture['user'])
            ->postJson('/api/saveProgress', [
                'attempt_id' => $fixture['attempt']->id,
                'state_version' => 0,
                'cell_inputs' => [
                    '2:1' => 'A',
                    '9:9' => 'Z', // Ismeretlen koordináta
                ],
            ]);

        $response->assertOk();
        
        $attempt = CrosswordAttempt::find($fixture['attempt']->id);
        $savedInputs = $attempt->grid_state['cell_inputs'] ?? [];
        
        $this->assertArrayHasKey('2:1', $savedInputs);
        $this->assertArrayNotHasKey('9:9', $savedInputs);
    }

    /**
     * Ellenőrzi, hogy a régi (v1) próbálkozási állapot migrálódik a v2 formátumba, és a rossz betűk is megmaradnak.
     * Illetve ellenőrzi, hogy a második getAttempt hívás nem változtatja meg a state_version-t és a grid_state-t.
     * 
     * @test
     */
    public function testLegacyMigrationPreservesWrongGuesses(): void
    {
        // 1. lépés: Létrehozunk egy próbálkozást régi (v1) struktúrával
        $fixture = $this->createAttemptFixture();

        $fixture['attempt']->update([
            'grid_state' => [
                'word_inputs' => [
                    $fixture['horizontal']->id => ['K', 'L', 'M', 'A'], // ALMA első betűje rossz (K)
                ]
            ]
        ]);

        // 2. lépés: Lekérjük a getAttempt végponton, ekkor le kell futnia a migrációnak
        $response = $this
            ->actingAs($fixture['user'])
            ->getJson('/api/getAttempt?crossword_id=' . $fixture['crossword']->id);

        $response->assertOk();

        // 3. lépés: Ellenőrizzük, hogy a v2 formátumban a rossz betű (K) is megmaradt
        $attempt = CrosswordAttempt::find($fixture['attempt']->id);
        $savedInputs = $attempt->grid_state['cell_inputs'] ?? [];

        $this->assertEquals(2, $attempt->grid_state['schema_version']);
        $this->assertEquals('K', $savedInputs['2:1']); // ALMA első betűje (2:1-es koordináta)
        $this->assertEquals('L', $savedInputs['2:2']);

        $versionAfterFirstLoad = $attempt->state_version;
        $stateAfterFirstLoad = $attempt->grid_state;

        $this->actingAs($fixture['user'])
            ->getJson('/api/getAttempt?crossword_id=' . $fixture['crossword']->id)
            ->assertOk();
        
        $attempt->refresh();

        $this->assertSame($versionAfterFirstLoad, $attempt->state_version, 'A state_version nem változott a második getAttempt hívás után');
        $this->assertSame($stateAfterFirstLoad, $attempt->grid_state, 'A grid_state nem változott a második getAttempt hívás után');
    }

    /**
     * Ellenőrzi, hogy a régi (v1) próbálkozási állapot migrálódik a v2 formátumba, és az ütköző értékek üresek legyenek.
     * 
     * @test
     */
    public function testLegacyConflictingIntersectionValuesBecomeEmpty(): void
    {
        $fixture = $this->createAttemptFixture();

        // V1 formátumban az ALMA (id 1) és ELV (id 2) metszéspontja a 2:2-es cella ('L' betű).
        // Itt megadunk egy konfliktust: az ALMA szerint ott 'X', az ELV szerint ott 'Y' van beírva.
        $fixture['attempt']->update([
            'grid_state' => [
                'word_inputs' => [
                    $fixture['horizontal']->id => ['A', 'X', 'M', 'A'],
                    $fixture['vertical']->id => ['E', 'Y', 'V'],
                ],
            ],
        ]);
        
        $this
            ->actingAs($fixture['user'])
            ->getJson('/api/getAttempt?crossword_id=' . $fixture['crossword']->id)
            ->assertOk();

        $attempt = CrosswordAttempt::find($fixture['attempt']->id);
        $savedInputs = $attempt->grid_state['cell_inputs'] ?? [];

        $this->assertArrayHasKey('2:2', $savedInputs);
        $this->assertEquals('', $savedInputs['2:2'], 'A metszéspontban ütköző értékek miatt az üres stringnek kell lennie a migráció után');
    }

    /**
     * Ellenőrzi, hogy a próbálkozás állapotának frissítése sikertelen, ha a kliens által küldött állapotverzió elavult.
     * 
     * @test
     */
    public function testStaleSaveProgressReturnsHttp409(): void
    {
        $fixture = $this->createAttemptFixture();

        $fixture['attempt']->update(['state_version' => 1]);

        $response = $this
            ->actingAs($fixture['user'])
            ->postJson('/api/saveProgress', [
                'attempt_id' => $fixture['attempt']->id,
                'state_version' => 0,
                'cell_inputs' => [],
            ]);

        $response
            ->assertConflict()
            ->assertJson([
                'success' => false,
                'message' => 'A próbálkozás állapota megváltozott, frissítse az oldalt!',
            ]);
    }

    /**
     * Ellenőrzi, hogy az elavult beacon a frissebb grid állapotot megőrzi, de a started_at mezőt nullázza.
     * 
     * @test
     */
    public function testStaleBeaconPreservesNewerGridStateButClearsStartedAt(): void
    {
        $fixture = $this->createAttemptFixture();
        
        // A szerveren már van egy frissebb (5-ös) verzió a saját értékeivel
        $fixture['attempt']->update([
            'state_version' => 5,
            'grid_state' => [
                'schema_version' => 2,
                'cell_inputs' => ['2:1' => 'A'], 
                'correct_entry_ids' => [],
            ]
        ]);

        // Érkezik egy beacon elavult (1-es) verzióval és eltérő adatokkal
        $response = $this
            ->actingAs($fixture['user'])
            ->postJson('/api/saveAndStopBeacon', [
                'attempt_id' => $fixture['attempt']->id,
                'state_version' => 1,
                'cell_inputs' => ['2:1' => 'Z'], 
            ]);

        $response->assertOk(); // Beacon nem dob 409-et

        $attempt = CrosswordAttempt::find($fixture['attempt']->id);
        
        // A grid adatai és a state_version NE változzanak meg a régi adatokra
        $this->assertEquals(5, $attempt->state_version);
        $this->assertEquals('A', $attempt->grid_state['cell_inputs']['2:1']);
        
        // Viszont a started_at-et nulláznia kell, azaz stopActiveInterval() lefutott
        $this->assertNull($attempt->started_at);
    }

    /**
     * Ellenőrzi, hogy a próbálkozás elhagyása során a jelenlegi eltelt időtartam hozzáadódik az elapsed_time mezőhöz.
     * 
     * @test
     */
    public function testAbandonStoresTheCurrentElapsedInterval(): void
    {
        // Befagyasztjuk az időt a teszt során, hogy ellenőrizni tudjuk az elapsed_time frissítését
        // Mivel random bukott/passelt a teszt attól függően, hogy 41 vagy 40 másodpercet kaptunk vissza backendről
        $fixedNow = Carbon::parse('2024-06-01 12:00:00');
        $this->travelTo($fixedNow);

        $fixture = $this->createAttemptFixture();

        // Visszatekerjük az időt 30 másodperccel ezelőttre
        $fixture['attempt']->update([
            'started_at' => now()->copy()->subSeconds(30),
            'elapsed_time' => 10
        ]);

        $response = $this
            ->actingAs($fixture['user'])
            ->postJson('/api/abandonAttempt', [
                'attempt_id' => $fixture['attempt']->id,
            ]);

        $response->assertOk();

        $attempt = CrosswordAttempt::find($fixture['attempt']->id);
        $this->assertEquals('abandoned', $attempt->status);
        $this->assertNull($attempt->started_at);
        $this->assertEquals(40, $attempt->elapsed_time); // 10 meglévő + 30 új = 40
    }

    /**
     * Ellenőrzi, hogy a befejezett vagy elhagyott próbálkozások nem indíthatók újra.
     * 
     * @test
     */
    public function testCompletedOrAbandonedAttemptsCannotBeStarted(): void
    {
        $fixture = $this->createAttemptFixture(status: 'completed');

        $responseCompleted = $this
            ->actingAs($fixture['user'])
            ->postJson('/api/startAttempt', [
                'attempt_id' => $fixture['attempt']->id,
            ]);

        $responseCompleted->assertConflict(); // AttemptStateConflict -> 409

        $fixture2 = $this->createAttemptFixture(status: 'abandoned');

        $responseAbandoned = $this
            ->actingAs($fixture2['user'])
            ->postJson('/api/startAttempt', [
                'attempt_id' => $fixture2['attempt']->id,
            ]);

        $responseAbandoned->assertConflict();
    }

    /**
     * Ellenőrzi, hogy az üres cella bemenetek is elfogadhatók, és nem okoznak hibát.
     * 
     * @test
     */
    public function testEmptyCellInputsObjectIsAccepted(): void
    {
        $fixture = $this->createAttemptFixture();

        $response = $this
            ->actingAs($fixture['user'])
            ->postJson('/api/saveProgress', [
                'attempt_id' => $fixture['attempt']->id,
                'state_version' => 0,
                'cell_inputs' => [], // Üres bemenet
            ]);

        $response->assertOk();
        
        $attempt = CrosswordAttempt::find($fixture['attempt']->id);
        $savedInputs = $attempt->grid_state['cell_inputs'] ?? [];

        // A normalizeCellInputs() minden érvényes koordinátát beállít üres stringre ('').
        // Az array_filter() kiszedi az üres stringeket, így ha utána üres a tömb, akkor minden üres volt.
        $this->assertEmpty(array_filter($savedInputs));
        
        // Plusz egy konkrét ellenőrzés a biztonság kedvéért
        $this->assertArrayHasKey('2:1', $savedInputs);
        $this->assertEquals('', $savedInputs['2:1']);
    }
}
