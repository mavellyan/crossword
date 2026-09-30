<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Clue;
use App\Models\Crossword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Enums\Direction;
use App\Enums\ValidationErrors;

class CrosswordFeatureTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Clue $clueHello;
    private Clue $clueWorld;
    private Clue $clueDork;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();

        // Készítünk néhány fix szót az ütközések teszteléséhez
        $this->clueHello = Clue::factory()->create(['solution' => 'HELLO']); // Hossz: 5
        $this->clueWorld = Clue::factory()->create(['solution' => 'WORLD']); // Hossz: 5
        $this->clueDork  = Clue::factory()->create(['solution' => 'DORK']);  // Hossz: 4
    }

    /**
     * Létrehozunk egy rejtvényt, amelyben a "HELLO" vízszintesen és a "WORLD" függőlegesen keresztezi egymást.
     * 
     * @test
     */
    public function testCreatingValidHorizontalAndVerticalLayout(): void
    {
        $payload = [
            'title' => 'Test Crossword',
            'entries' => [
                ['clue_id' => $this->clueHello->id, 'direction' => Direction::HORIZONTAL, 'start_row' => 1, 'start_col' => 0],
                ['clue_id' => $this->clueWorld->id, 'direction' => Direction::VERTICAL, 'start_row' => 0, 'start_col' => 4],
            ],
        ];

        $response = $this->actingAs($this->user)->postJson('/api/createCrossword', $payload);

        $response->assertCreated()
                 ->assertJsonPath('success', true);

        $crosswordId = $response->json('crossword.id');

        $this->assertDatabaseHas('crosswords', [
            'id' => $crosswordId,
            'title' => 'Test Crossword',
            'main_solution' => null,
        ]);

        $this->assertDatabaseHas('crossword_clues', [
            'crossword_id' => $crosswordId,
            'clue_id' => $this->clueHello->id,
            'direction' => Direction::HORIZONTAL->value,
            'start_row' => 1,
            'start_col' => 0,
        ]);

        $this->assertDatabaseHas('crossword_clues', [
            'crossword_id' => $crosswordId,
            'clue_id' => $this->clueWorld->id,
            'direction' => Direction::VERTICAL->value,
            'start_row' => 0,
            'start_col' => 4,
        ]);

        $this->assertDatabaseCount('crossword_clues', 2);
    }

    /**
     * Ellenőrzi, hogy a fordított kérési sorrendben küldött bejegyzések is a megfelelő koordinátákkal mentődnek el.
     *
     * @test
     */
    public function testCreatingTheSameLayoutInDifferentRequestOrder(): void
    {
        $payload = [
            'title' => 'Different Order Crossword',
            'entries' => [
                ['clue_id' => $this->clueWorld->id, 'direction' => Direction::VERTICAL, 'start_row' => 0, 'start_col' => 4],
                ['clue_id' => $this->clueHello->id, 'direction' => Direction::HORIZONTAL, 'start_row' => 1, 'start_col' => 0],
            ],
        ];

        $response = $this
            ->actingAs($this->user)
            ->postJson('/api/createCrossword', $payload)
            ->assertCreated();

        $crosswordId = $response->json('crossword.id');

        $this->assertDatabaseHas('crossword_clues', [
            'crossword_id' => $crosswordId,
            'clue_id' => $this->clueWorld->id,
            'direction' => Direction::VERTICAL->value,
            'start_row' => 0,
            'start_col' => 4,
        ]);

        $this->assertDatabaseHas('crossword_clues', [
            'crossword_id' => $crosswordId,
            'clue_id' => $this->clueHello->id,
            'direction' => Direction::HORIZONTAL->value,
            'start_row' => 1,
            'start_col' => 0,
        ]);

        $this->assertSame(
            2,
            Crossword::query()->findOrFail($crosswordId)->crosswordClues()->count(),
        );
    }

    /**
     * Ütköző metszetek elutasítása (pl. a "DORK" függőleges szó ütközik a "HELLO" vízszintes szóval).
     * 
     * @test
     */
    public function testRejectingConflictingIntersections(): void
    {
        $payload = [
            'title' => 'Conflict',
            'entries' => [
                ['clue_id' => $this->clueHello->id, 'direction' => Direction::HORIZONTAL, 'start_row' => 1, 'start_col' => 0],
                // A "DORK" függőleges szó ütközik a "HELLO" vízszintes szóval, mert az 1. betűje 'D', ami nem egyezik a "HELLO" 1. betűjével ('H')
                ['clue_id' => $this->clueDork->id, 'direction' => Direction::VERTICAL, 'start_row' => 1, 'start_col' => 0],
            ],
        ];

        $response = $this->actingAs($this->user)->postJson('/api/createCrossword', $payload);

        $response->assertStatus(422)
                 ->assertJsonFragment(['success' => false])
                 ->assertJsonPath('errors.0.code', ValidationErrors::LETTER_CONFLICT->value);
    }

    /**
     * Szétkapcsolt layout elutasítása (pl. a "HELLO" és a "WORLD" túl messze vannak egymástól, nem érnek össze).
     * 
     * @test
     */
    public function testRejectingDisconnectedLayouts(): void
    {
        $payload = [
            'title' => 'Disconnected',
            'entries' => [
                ['clue_id' => $this->clueHello->id, 'direction' => Direction::HORIZONTAL, 'start_row' => 0, 'start_col' => 0],
                ['clue_id' => $this->clueWorld->id, 'direction' => Direction::HORIZONTAL, 'start_row' => 5, 'start_col' => 5], // Túl messze van, nem érnek össze
            ],
        ];

        $response = $this->actingAs($this->user)->postJson('/api/createCrossword', $payload);

        $response->assertUnprocessable()
                 ->assertJsonPath('success', false)
                 ->assertJsonPath('errors.0.code', ValidationErrors::DISCONNECTED_LAYOUT->value);
    }

    /**
     * Duplikált clue_id-k elutasítása (pl. ugyanaz a szó kétszer szerepel a layoutban).
     * 
     * @test
     */
    public function testRejectingDuplicateClueIds(): void
    {
        $payload = [
            'title' => 'Duplicate Clues',
            'entries' => [
                ['clue_id' => $this->clueHello->id, 'direction' => Direction::HORIZONTAL, 'start_row' => 0, 'start_col' => 0],
                ['clue_id' => $this->clueHello->id, 'direction' => Direction::VERTICAL, 'start_row' => 0, 'start_col' => 0], 
            ],
        ];

        $response = $this->actingAs($this->user)->postJson('/api/createCrossword', $payload);

        // A FormRequest distinct validációja elkapja (422)
        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['entries.0.clue_id']);
    }

    /**
     * Nem létező clue_id-k elutasítása (pl. egy olyan szó, ami nincs az adatbázisban).
     * 
     * @test
     */
    public function testRejectingNonexistentClueIds(): void
    {
        $payload = [
            'title' => 'Nonexistent Clue',
            'entries' => [
                ['clue_id' => 999999, 'direction' => Direction::HORIZONTAL, 'start_row' => 0, 'start_col' => 0],
                ['clue_id' => $this->clueWorld->id, 'direction' => Direction::VERTICAL, 'start_row' => 0, 'start_col' => 4],
            ],
        ];

        $response = $this->actingAs($this->user)->postJson('/api/createCrossword', $payload);

        // A FormRequest exists:clues,id validációja elkapja (422)
        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['entries.0.clue_id']);
    }

    /**
     * Rács területén kívüli koordináták elutasítása (pl. start_row vagy start_col túl nagy).
     * 
     * @test
     */
    public function testRejectingOutOfBoundsCoordinates(): void
    {
        $payload = [
            'title' => 'Out of bounds',
            'entries' => [
                ['clue_id' => $this->clueHello->id, 'direction' => Direction::HORIZONTAL, 'start_row' => 20, 'start_col' => 0], // max:19 lehet
                ['clue_id' => $this->clueWorld->id, 'direction' => Direction::VERTICAL, 'start_row' => 0, 'start_col' => 4],
            ],
        ];

        $response = $this->actingAs($this->user)->postJson('/api/createCrossword', $payload);

        // A FormRequest max:19 validációja elkapja
        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['entries.0.start_row']);
    }

    /**
     * Érvénytelen szó esetén el kell buknia a validációnak, nem szabad létrejönni a rejtvénynek
     * 
     * @test
     */
    public function testInvalidLayoutIsNotPersisted(): void
    {
        $payload = [
            'title' => 'Persistence Test',
            'entries' => [
                ['clue_id' => $this->clueHello->id, 'direction' => Direction::HORIZONTAL, 'start_row' => 0, 'start_col' => 0],
                ['clue_id' => $this->clueWorld->id, 'direction' => Direction::HORIZONTAL, 'start_row' => 5, 'start_col' => 5], // Disconnected
            ],
        ];

        $this->actingAs($this->user)->postJson('/api/createCrossword', $payload);

        // Mivel a layout validáció elbukik, az adatbázisba nem kerül be a rejtvény
        $this->assertDatabaseMissing('crosswords', ['title' => 'Persistence Test']);
    }

    /**
     * Ellenőrzi, hogy a legacy clue_ids létrehozás elmenti, igazítja és publikálhatóvá teszi a generált bejegyzéseket.
     *
     * @test
     */
    public function testLegacyClueIdsCreationStillWorks(): void
    {
        $payload = [
            'title' => 'Legacy Crossword',
            'main_solution' => 'LOL',
            'clue_ids' => [
                $this->clueHello->id,
                $this->clueDork->id,
                $this->clueWorld->id,
            ],
        ];

        $response = $this
            ->actingAs($this->user)
            ->postJson('/api/createCrossword', $payload)
            ->assertCreated();

        $crosswordId = $response->json('crossword.id');
        $crossword = Crossword::query()->findOrFail($crosswordId);
        $entries = $crossword->crosswordClues()
            ->where('is_main', false)
            ->orderBy('start_row')
            ->get();

        $this->assertSame('LOL', $crossword->main_solution);
        $this->assertCount(3, $entries);
        $this->assertSame([0, 1, 2], $entries->pluck('start_row')->all());
        $this->assertSame([1, 2, 0], $entries->pluck('start_col')->all());

        foreach ($entries as $entry) {
            $this->assertSame(Direction::HORIZONTAL->value, $entry->getDirection());
        }

        $intersectionOffsets = [
            $this->clueHello->id => mb_strpos('HELLO', 'L'),
            $this->clueDork->id => mb_strpos('DORK', 'O'),
            $this->clueWorld->id => mb_strpos('WORLD', 'L'),
        ];

        $mainColumns = $entries
            ->map(fn ($entry) => $entry->start_col + $intersectionOffsets[$entry->clue_id])
            ->unique()
            ->values();

        $this->assertSame([3], $mainColumns->all());

        $this
            ->actingAs($this->user)
            ->patchJson('/api/setVisibility', [
                'id' => $crosswordId,
                'is_public' => true,
            ])
            ->assertOk()
            ->assertJsonPath('is_public', true);

        $this->assertTrue($crossword->fresh()->is_public);
    }

    /**
     * A user a saját, privát rejtvényét szerkesztheti
     * 
     * @test
     */
    public function testOwnerCanUpdatePrivateUntouchedCrossword(): void
    {
        $crossword = Crossword::factory()->create(['user_id' => $this->user->id, 'is_public' => false]);

        $oldEntry = $crossword->crosswordClues()->create([
            'clue_id' => $this->clueDork->id,
            'direction' => Direction::HORIZONTAL,
            'start_row' => 8,
            'start_col' => 2,
            'is_main' => false,
        ]);

        $oldEntry->save();

        $crossword->save();

        $payload = [
            'id' => $crossword->id,
            'title' => 'Updated Title',
            'entries' => [
                ['clue_id' => $this->clueHello->id, 'direction' => Direction::HORIZONTAL, 'start_row' => 1, 'start_col' => 0],
                ['clue_id' => $this->clueWorld->id, 'direction' => Direction::VERTICAL, 'start_row' => 0, 'start_col' => 4],
            ],
        ];

        $response = $this->actingAs($this->user)->putJson('/api/updateCrossword', $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('crosswords', ['id' => $crossword->id, 'title' => 'Updated Title']);

        $this->assertDatabaseMissing('crossword_clues', [
            'id' => $oldEntry->id,
        ]);

        $this->assertDatabaseHas('crossword_clues', [
            'crossword_id' => $crossword->id,
            'clue_id' => $this->clueHello->id,
            'start_row' => 1,
            'start_col' => 0,
        ]);

        $this->assertDatabaseHas('crossword_clues', [
            'crossword_id' => $crossword->id,
            'clue_id' => $this->clueWorld->id,
            'start_row' => 0,
            'start_col' => 4,
        ]);

        $this->assertSame(2, $crossword->crosswordClues()->count());
    }

    /**
     * Ha a user a saját rejtvényét szerkeszti, de hibás a layout, akkor 422-es hibát kap, és rollback történik, azaz nem változik a rejtvény.
     * 
     * @test
     */
    public function testInvalidCrosswordUpdate(): void
    {
        $crossword = Crossword::factory()->create(['user_id' => $this->user->id, 'title' => 'Original Title', 'is_public' => false]);

        $entry1 = $crossword->crosswordClues()->create([
            'clue_id' => $this->clueDork->id,
            'direction' => Direction::HORIZONTAL,
            'start_row' => 8,
            'start_col' => 2,
            'is_main' => false,
        ]);

        $entry2 = [
            'clue_id' => $this->clueHello->id,
            'direction' => Direction::VERTICAL,
            'start_row' => 1,
            'start_col' => 0,
            'is_main' => false,
        ];

        $entry3 = [
            'clue_id' => $this->clueWorld->id,
            'direction' => Direction::VERTICAL,
            'start_row' => 0,
            'start_col' => 4,
            'is_main' => false,
        ];

        $payload = [
            'id' => $crossword->id,
            'title' => 'Updated Title',
            'entries' => [
                $entry2,
                $entry3,
            ],
        ];

        $response = $this->actingAs($this->user)->putJson('/api/updateCrossword', $payload);

        $response->assertStatus(422);
        $this->assertDatabaseHas('crosswords', ['id' => $crossword->id, 'title' => 'Original Title']); // A cím nem változott

        $this->assertDatabaseMissing('crossword_clues', [
            'clue_id' => $entry2['clue_id'],
        ]);

        $this->assertDatabaseHas('crossword_clues', [
            'crossword_id' => $crossword->id,
            'clue_id' => $entry1->clue_id,
            'start_row' => 8,
            'start_col' => 2,
        ]);

        $this->assertSame(1, $crossword->crosswordClues()->count());
    }

    /**
     * Más rejtvényét nem szerkesztheti a user, csak a sajátját
     * 
     * @test
     */
    public function testAnotherUserCannotUpdateSomebodyElsesCrossword(): void
    {
        $owner = User::factory()->create();
        $crossword = Crossword::factory()->create(['user_id' => $owner->id, 'is_public' => false]);

        $payload = [
            'id' => $crossword->id,
            'title' => 'Updated Title',
            'entries' => [
                ['clue_id' => $this->clueHello->id, 'direction' => Direction::HORIZONTAL, 'start_row' => 1, 'start_col' => 0],
                ['clue_id' => $this->clueWorld->id, 'direction' => Direction::VERTICAL, 'start_row' => 0, 'start_col' => 4],
            ],
        ];

        // Bejelentkezve a saját $this->user fiókunkkal próbáljuk másét módosítani
        $response = $this->actingAs($this->user)->putJson('/api/updateCrossword', $payload);


        // A kontroller AuthorizationException-t kap és 403-as választ dob rá
        $response->assertForbidden()
                 ->assertJsonPath('success', false);
    }

    /**
     * Más user/vendég nem tudja megnyitni a privát rejtvényt, csak a tulajdonosa
     * 
     * @test
     */
    public function testAnotherUserCannotOpenPrivateCrossword(): void
    {
        $owner = User::factory()->create();

        $crossword = Crossword::factory()->create([
            'user_id' => $owner->id,
            'is_public' => false,
        ]);

        $this->actingAs($this->user)
            ->getJson('/api/getCrossword?id=' . $crossword->id)
            ->assertNotFound();
        
        // Vendég felhasználó sem nyithatja meg a privát rejtvényt
        $this->getJson('/api/getCrossword?id=' . $crossword->id)
            ->assertNotFound();
    }

    /**
     * Nyilvános rejtvény nem szerkeszthető
     * 
     * @test
     */
    public function testPublicCrosswordCannotBeUpdated(): void
    {
        $crossword = Crossword::factory()->create(['user_id' => $this->user->id, 'is_public' => true]);

        $payload = [
            'id' => $crossword->id,
            'title' => 'Updated Title',
            'entries' => [
                ['clue_id' => $this->clueHello->id, 'direction' => Direction::HORIZONTAL, 'start_row' => 1, 'start_col' => 0],
                ['clue_id' => $this->clueWorld->id, 'direction' => Direction::VERTICAL, 'start_row' => 0, 'start_col' => 4],
            ],
        ];

        $response = $this->actingAs($this->user)->putJson('/api/updateCrossword', $payload);

        $response->assertStatus(409)
                 ->assertJsonFragment(['message' => 'Ez a rejtvény már nyilvános vagy rendelkezik próbálkozásokkal, így nem módosítható.']);
    }

    /**
     * Már meglévő, valódi próbálkozással (nem 'not_started') rendelkező rejtvény nem szerkeszthető
     * 
     * @test
     */
    public function testCrosswordWithRealAttemptCannotBeUpdated(): void
    {
        $crossword = Crossword::factory()->create(['user_id' => $this->user->id, 'is_public' => false]);
        
        // Csinálunk egy valódi (pl. in_progress) próbálkozást
        $crossword->attempts()->create([
            'user_id' => $this->user->id,
            'status' => 'in_progress'
        ]);

        $payload = [
            'id' => $crossword->id,
            'title' => 'Updated Title',
            'entries' => [
                ['clue_id' => $this->clueHello->id, 'direction' => Direction::HORIZONTAL, 'start_row' => 1, 'start_col' => 0],
                ['clue_id' => $this->clueWorld->id, 'direction' => Direction::VERTICAL, 'start_row' => 0, 'start_col' => 4],
            ],
        ];

        $response = $this->actingAs($this->user)->putJson('/api/updateCrossword', $payload);

        $response->assertStatus(409);
    }

    /**
     * 'not_started' próbálkozással rendelkező rejtvény szerkeszthető, mivel az még nem számít valódi próbálkozásnak
     * 
     * @test
     */
    public function testNotStartedAttemptDoesNotBlockEditing(): void
    {
        $crossword = Crossword::factory()->create(['user_id' => $this->user->id, 'is_public' => false]);
        
        // Csak "not_started" státuszú próbálkozás van (pl. csak megnyitotta a usert, de nem írt be betűt)
        $crossword->attempts()->create([
            'user_id' => $this->user->id,
            'status' => 'not_started'
        ]);

        $payload = [
            'id' => $crossword->id,
            'title' => 'Updated Title',
            'entries' => [
                ['clue_id' => $this->clueHello->id, 'direction' => Direction::HORIZONTAL, 'start_row' => 1, 'start_col' => 0],
                ['clue_id' => $this->clueWorld->id, 'direction' => Direction::VERTICAL, 'start_row' => 0, 'start_col' => 4],
            ],
        ];
        $response = $this->actingAs($this->user)->putJson('/api/updateCrossword', $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('crosswords', ['id' => $crossword->id, 'title' => 'Updated Title']);
    }

    /**
     * A frontendnek (solver és lista) elküldött rejtvény nem tartalmazhatja a main_solution vagy a clue-k solution mezőjét, hogy ne szivárogjon ki a megoldás.
     * 
     * @test
     */
    public function testPublicSolverResponseContainsNoSolutionOrMainSolution(): void
    {
        $crossword = Crossword::factory()->create([
            'user_id' => $this->user->id, 
            'is_public' => true,
            'main_solution' => 'TITOK'
        ]);

        $crossword->crosswordClues()->create([
            'clue_id' => $this->clueHello->id,
            'direction' => Direction::HORIZONTAL,
            'start_row' => 1,
            'start_col' => 0,
            'is_main' => false,
        ]);

        // Teszteljük a GetCrossword végpontot, ami a frontenden a kitöltőnek (solver) jelenik meg
        $response = $this->actingAs($this->user)->getJson("/api/getCrossword?id=" . $crossword->id);

        $response->assertStatus(200);

        $publicCrossword = $response->json('crossword');

        $this->assertArrayNotHasKey('main_solution', $publicCrossword);

        foreach ($publicCrossword['words'] as $entry) {
            $this->assertArrayNotHasKey('solution', $entry);
        }

        foreach ($publicCrossword['grid'] as $row) {
            foreach ($row as $cell) {
                $this->assertContains($cell, ['#', null], true); // Csak '#' (fekete cella) vagy null (üres cella) lehet, nem lehet benne a betű
            }
        }

        $response2 = $this->actingAs($this->user)->getJson('/api/listCrosswords');

        $response2->assertStatus(200);

        foreach ($response2->json('crosswords') as $crossword) {
            $this->assertArrayNotHasKey('main_solution', $crossword);
        }
    }

    /**
     * A frontend szerkesztőnek (editor) elküldött rejtvény tartalmazza a main_solution és a clue-k solution mezőjét, hogy a szerkesztő lássa a megoldást.
     * 
     * @test
     */
    public function testEditResponseContainsCoordinatesAndSolutions(): void
    {
        $crossword = Crossword::factory()->create([
            'user_id' => $this->user->id, 
            'is_public' => false,
            'main_solution' => 'TITOK'
        ]);

        $crossword->crosswordClues()->create([
            'clue_id' => $this->clueHello->id,
            'direction' => Direction::HORIZONTAL,
            'start_row' => 1,
            'start_col' => 0,
            'is_main' => false,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/getCrosswordForEdit?id=' . $crossword->id);

        $response->assertStatus(200)
                 ->assertJsonPath('crossword.main_solution', 'TITOK')
                 ->assertJsonStructure([
                     'crossword' => [
                         'entries' => [
                             '*' => [
                                 'clue_id',
                                 'solution', // A szerkesztésnél VISSZA KELL adni a megoldást
                                 'direction',
                                 'start_row', // És a koordinátákat
                                 'start_col'
                             ]
                         ]
                     ]
                 ]);
    }
}