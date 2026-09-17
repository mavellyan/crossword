<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Domain\Crossword\Placement;
use App\Enums\Direction;
use App\Services\PlacementValidator;
use App\Enums\ValidationErrors;

class CandidateValidatorTest extends TestCase
{
    /**
     * Leteszteli, hogy a Placement osztály cells() metódusa helyesen adja vissza a cellákat a megadott kezdő koordináták és irány alapján.
     * 
     * @test
     */
    public function testPlacementCells(): void
    {
        $placement = new Placement(
            id: null,
            clueId: 1,
            answer: 'TŰZ',
            direction: Direction::HORIZONTAL,
            startRow: 2,
            startCol: 4
        );

        $this->assertSame(
            ['T', 'Ű', 'Z'],
            array_column($placement->cells(), 'letter')
        );

        $placement2 = new Placement(
            id: null,
            clueId: 1,
            answer: 'tűz',
            direction: Direction::HORIZONTAL,
            startRow: 2,
            startCol: 4
        );

        $this->assertSame(
            ['T', 'Ű', 'Z'],
            array_column($placement2->cells(), 'letter')
        );
    }

    /**
     * Teszteli, hogy a kis- és nagybetűs válaszok megfelelően normalizálódnak-e,
     * és emiatt a metszéspont valid lesz.
     * 
     * @test
     */
    public function testMixedCaseAnswerIsNormalized(): void
    {
        $validator = new PlacementValidator();

        // 1. szó 'TŰZ' vízszintesen, 2. sor, 4-6. oszlop
        $horizontal_placement = new Placement(
            id: null,
            clueId: 1,
            answer: 'TŰZ',
            direction: Direction::HORIZONTAL,
            startRow: 2,
            startCol: 4
        );

        // 2. szó 'zEBrA' függőlegesen, a kevert kis- és nagybetűk ellenére is jó a metszés
        $vertical_placement = new Placement(
            id: null,
            clueId: 2,
            answer: 'zEBrA',
            direction: Direction::VERTICAL,
            startRow: 2,
            startCol: 6
        );

        $result = $validator->validateCandidate([$horizontal_placement], $vertical_placement);

        $this->assertTrue($result->valid);
        $this->assertEmpty($result->errors);
        $this->assertSame(1, $result->intersectionCount);
    }

    /**
     * Üres (0 karakter), egybetűs (1 karakter) és túl hosszú (21 karakter) elhelyezések tesztelése.
     * Mind elutasításra kerül (rejected).
     * 
     * @test
     */
    public function testAnswerLengthValidation(): void
    {
        $validator = new PlacementValidator();

        // 1. Üres szó, elutasításra kerül
        $empty_placement = new Placement(
            id: null,
            clueId: 1,
            answer: '',
            direction: Direction::HORIZONTAL,
            startRow: 2,
            startCol: 4
        );

        $result_empty = $validator->validateCandidate([], $empty_placement);

        $this->assertFalse($result_empty->valid);
        $this->assertNotEmpty($result_empty->errors);
        $this->assertSame($result_empty->errors[0]['code'], ValidationErrors::ANSWER_TOO_SHORT);

        // 2. 1 karakteres szó, elutasításra kerül
        $short_placement = new Placement(
            id: null,
            clueId: 1,
            answer: 'A',
            direction: Direction::HORIZONTAL,
            startRow: 2,
            startCol: 4
        );

        $result_short = $validator->validateCandidate([], $short_placement);

        $this->assertFalse($result_short->valid);
        $this->assertNotEmpty($result_short->errors);
        $errorCodes = array_column($result_short->errors, 'code');
        $this->assertContains(ValidationErrors::ANSWER_TOO_SHORT, $errorCodes);

        // 3. szó 'ABCDEFGHIJKLMNOPQRSTUVWXYZ' (26 karakter), hibás, túl hosszú
        $long_placement = new Placement(
            id: null,
            clueId: 2,
            answer: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            direction: Direction::HORIZONTAL,
            startRow: 2,
            startCol: 4
        );

        $result_long = $validator->validateCandidate([], $long_placement);

        $this->assertFalse($result_long->valid);
        $this->assertNotEmpty($result_long->errors);
        $errorCodesLong = array_column($result_long->errors, 'code');
        $this->assertContains(ValidationErrors::ANSWER_TOO_LONG, $errorCodesLong);
    }

    /**
     * Teszteli, hogy a PlacementValidator osztály validateCandidate() metódusa helyesen validálja az első elhelyezést a rácson.
     * Nem szabad, hogy hibát dobjon, mivel nincs más elhelyezés a rácson, rácson belül helyezzük el, és nem szabad metszéspontot találnia sem.
     * 
     * @test
     */
    public function testFirstPlacementValidation(): void
    {
        $validator = new PlacementValidator();

        // 1. szó 'TŰZ' vízszintesen, 2. sor, 4-6. oszlop
        $horizontal_placement = new Placement(
            id: null,
            clueId: 1,
            answer: 'TŰZ',
            direction: Direction::HORIZONTAL,
            startRow: 2,
            startCol: 4
        );

        $result1 = $validator->validateCandidate([], $horizontal_placement);

        $this->assertTrue($result1->valid);
        $this->assertEmpty($result1->errors);
        $this->assertSame(0, $result1->intersectionCount);

        // 2. szó 'ZEBRA' függőlegesen, 1. sor, 5-9. oszlop
        $vertical_placement = new Placement(
            id: null,
            clueId: 2,
            answer: 'ZEBRA',
            direction: Direction::VERTICAL,
            startRow: 1,
            startCol: 5
        );

        $result2 = $validator->validateCandidate([], $vertical_placement);

        $this->assertTrue($result2->valid);
        $this->assertEmpty($result2->errors);
        $this->assertSame(0, $result2->intersectionCount);
    }

    /**
     * Vízszintes és függőleges elhelyezés metszéspontjának tesztelése, ahol a metszéspontban ugyanaz a betű van (Z, 2.sor 6.oszlop).
     * A metszéspontnak helyesnek kell lennie, és nem szabad, hogy hibát dobjon.
     * 
     * @test
     */
    public function testValidIntersection(): void
    {
        $validator = new PlacementValidator();

        // 1. szó 'TŰZ' vízszintesen, 2. sor, 4-6. oszlop
        $horizontal_placement = new Placement(
            id: null,
            clueId: 1,
            answer: 'TŰZ',
            direction: Direction::HORIZONTAL,
            startRow: 2,
            startCol: 4
        );

        // 2. szó 'ZEBRA' függőlegesen, 6. oszlop, 2-6. sor (a metszéspontban ugyanaz a betű van, Z, 2.sor 6.oszlop)
        $vertical_placement = new Placement(
            id: null,
            clueId: 2,
            answer: 'ZEBRA',
            direction: Direction::VERTICAL,
            startRow: 2,
            startCol: 6
        );

        $result = $validator->validateCandidate([$horizontal_placement], $vertical_placement);

        $this->assertTrue($result->valid);
        $this->assertEmpty($result->errors);
        $this->assertSame(1, $result->intersectionCount);
    }

    /**
     * Vízszintes és függőleges elhelyezés metszéspontjának tesztelése, ahol a metszéspontban nem ugyanaz a betű van (Z vs K).
     * Hibát kell dobnia, és nem lehet valid.
     * 
     * @test
     */
    public function testInvalidIntersection(): void
    {
        $validator = new PlacementValidator();

        // 1. szó 'TŰZ' vízszintesen, 2. sor, 4-6. oszlop
        $horizontal_placement = new Placement(
            id: null,
            clueId: 1,
            answer: 'TŰZ',
            direction: Direction::HORIZONTAL,
            startRow: 2,
            startCol: 4
        );

        // 2. szó 'KUTYA' függőlegesen, 6. oszlop, 2-6. sor (a metszéspontban nem ugyanaz a betű van, Z vs K)
        $vertical_placement = new Placement(
            id: null,
            clueId: 2,
            answer: 'KUTYA',
            direction: Direction::VERTICAL,
            startRow: 2,
            startCol: 6
        );

        $result = $validator->validateCandidate([$horizontal_placement], $vertical_placement);

        $this->assertFalse($result->valid);
        $this->assertNotEmpty($result->errors);
        $errorCodes = array_column($result->errors, 'code');
        $this->assertContains(ValidationErrors::LETTER_CONFLICT, $errorCodes);
        $this->assertSame(0, $result->intersectionCount);
    }

    /**
     * Ugyanazon irányú elhelyezések átfedésének tesztelése, ahol a vízszintes elhelyezések átfedik egymást.
     * Azonos irányú átfedés (same-direction overlap) 0 valódi metszéspontot eredményez.
     * 
     * @test
     */
    public function testSameDirectionOverlap(): void
    {
        $validator = new PlacementValidator();

        // 1. szó 'TŰZ' vízszintesen, 2. sor, 4-6. oszlop
        $horizontal_placement = new Placement(
            id: null,
            clueId: 1,
            answer: 'TŰZ',
            direction: Direction::HORIZONTAL,
            startRow: 2,
            startCol: 4
        );

        // 2. szó 'TŰZ' vízszintesen, 2. sor, 4-6. oszlop (ugyanaz a hely, ugyanaz az irány)
        $overlapping_horizontal_placement = new Placement(
            id: null,
            clueId: 2,
            answer: 'TŰZ',
            direction: Direction::HORIZONTAL,
            startRow: 2,
            startCol: 4
        );

        $result = $validator->validateCandidate([$horizontal_placement], $overlapping_horizontal_placement);

        $this->assertFalse($result->valid);
        $this->assertNotEmpty($result->errors);
        $errorCodes = array_column($result->errors, 'code');
        $this->assertContains(ValidationErrors::SAME_DIRECTION_OVERLAP, $errorCodes);
        $this->assertSame(0, $result->intersectionCount);
    }

    /**
     * Duplán foglalt cella teszt: 
     * Hozzáadunk egy érvényes vízszintes, majd egy érvényes függőleges szót, amik metszik egymást.
     * Ezután validálunk egy harmadik (vízszintes) szót ugyanezen a metszésponton. 
     * Elvárás: SAME_DIRECTION_OVERLAP hiba.
     * 
     * @test
     */
    public function testDoubleOccupiedCell(): void
    {
        $validator = new PlacementValidator();

        // 1. Érvényes vízszintes (ALMA az 5. sorban, 5-től 8. oszlopig)
        $horizontal_existing = new Placement(
            id: 1,
            clueId: 1,
            answer: 'ALMA',
            direction: Direction::HORIZONTAL,
            startRow: 5,
            startCol: 5
        );
        // 2. Érvényes függőleges, ami keresztezi az ALMA-t az első betűjénél (5, 5 cellában 'A' betűvel)
        $vertical_existing = new Placement(
            id: 2,
            clueId: 2,
            answer: 'AKNA',
            direction: Direction::VERTICAL,
            startRow: 5,
            startCol: 5
        );

        // 3. Harmadik szó, szintén vízszintes, át akar menni ugyanazon a (5, 5) cellán.
        $candidate = new Placement(
            id: null,
            clueId: 3,
            answer: 'ARANY',
            direction: Direction::HORIZONTAL,
            startRow: 5,
            startCol: 5
        );

        $result = $validator->validateCandidate([$horizontal_existing, $vertical_existing], $candidate);

        $this->assertFalse($result->valid);
        $this->assertNotEmpty($result->errors);
        
        // Elvárjuk a SAME_DIRECTION_OVERLAP hibát, mivel már van egy vízszintes szó ezen a cellán.
        $errorCodes = array_column($result->errors, 'code');
        $this->assertContains(ValidationErrors::SAME_DIRECTION_OVERLAP, $errorCodes);
    }

    /**
     * Teszteli, hogy a validáció működése nem változtatja-e meg a bemeneti (már meglévő) elhelyezéseket.
     * 
     * @test
     */
    public function testValidationDoesNotMutateExistingPlacements(): void
    {
        $validator = new PlacementValidator();

        // 1. Már meglévő elhelyezés a rácson
        $existing_placement = new Placement(
            id: 1,
            clueId: 1,
            answer: 'KIRÁZ',
            direction: Direction::HORIZONTAL,
            startRow: 1,
            startCol: 3
        );
        
        $existingPlacements = [$existing_placement];
        
        // Serializáljuk a kiindulási állapotot mély-összehasonlításhoz
        $originalData = serialize($existingPlacements);

        // 2. Új elhelyezés, ami validálásra kerül
        $candidate = new Placement(
            id: null,
            clueId: 2,
            answer: 'TŰZ',
            direction: Direction::VERTICAL,
            startRow: 1,
            startCol: 4
        );

        $validator->validateCandidate($existingPlacements, $candidate);

        $this->assertSame($originalData, serialize($existingPlacements));
    }

    /**
     * Ellenőrzi a negatív koordinátákat.
     * 
     * @test
     */
    public function testNegativeCoordinates(): void
    {
        $validator = new PlacementValidator();

        $negative_placement = new Placement(
            id: null,
            clueId: 1,
            answer: 'TŰZ',
            direction: Direction::HORIZONTAL,
            startRow: -1,
            startCol: 4
        );

        $result_one = $validator->validateCandidate([], $negative_placement);

        $this->assertFalse($result_one->valid);
        $this->assertNotEmpty($result_one->errors);
        $errorCodes = array_column($result_one->errors, 'code');
        $this->assertContains(ValidationErrors::NEGATIVE_COORDINATE, $errorCodes);
        $this->assertSame(0, $result_one->intersectionCount);
    }

    /**
     * Teszteli, hogy ha a rács utolsó sorának/oszlopának indexén (maximumRows / maximumCols) kezdődik egy elhelyezés,
     * akkor az elutasításra kerül (OUT_OF_BOUNDS), mivel az már kiesik.
     * 
     * @test
     */
    public function testStartAtMaximumBoundariesRejected(): void
    {
        $validator = new PlacementValidator();

        // Vízszintes kezdés a maximum sorban
        $horizontalCandidate = new Placement(
            id: null,
            clueId: 1,
            answer: 'ALMA',
            direction: Direction::HORIZONTAL,
            startRow: 20,
            startCol: 0
        );

        $res1 = $validator->validateCandidate([], $horizontalCandidate, 20, 20);

        $this->assertFalse($res1->valid);
        $errorCodes1 = array_column($res1->errors, 'code');
        $this->assertContains(ValidationErrors::OUT_OF_BOUNDS, $errorCodes1);

        // Függőleges kezdés a maximum oszlopban
        $verticalCandidate = new Placement(
            id: null,
            clueId: 2,
            answer: 'ALMA',
            direction: Direction::VERTICAL,
            startRow: 0,
            startCol: 20
        );

        $res2 = $validator->validateCandidate([], $verticalCandidate, 20, 20);

        $this->assertFalse($res2->valid);
        $errorCodes2 = array_column($res2->errors, 'code');
        $this->assertContains(ValidationErrors::OUT_OF_BOUNDS, $errorCodes2);
    }

    /**
     * Teszteli, hogy a pontosan a rács széléig (19. index) érő elhelyezések (20x20-as grid esetén) érvényesek.
     * 
     * @test
     */
    public function testEntryEndingExactlyAtEdgeIsValid(): void
    {
        $validator = new PlacementValidator();

        // Hossza 5, start: 15 -> 15, 16, 17, 18, 19. Pontosan a belső határon végződik.
        $horizontalCandidate = new Placement(
            id: null,
            clueId: 1,
            answer: 'ALMAS',
            direction: Direction::HORIZONTAL,
            startRow: 0,
            startCol: 15
        );

        $res1 = $validator->validateCandidate([], $horizontalCandidate, 20, 20);

        $this->assertTrue($res1->valid);

        // Ugyanez függőlegesen.
        $verticalCandidate = new Placement(
            id: null,
            clueId: 2,
            answer: 'ALMAS',
            direction: Direction::VERTICAL,
            startRow: 15,
            startCol: 0
        );

        $res2 = $validator->validateCandidate([], $verticalCandidate, 20, 20);

        $this->assertTrue($res2->valid);
    }

    /**
     * Teszteli, hogy a rács szélét túllépő (20. indexen végződő) elhelyezések elutasításra kerülnek.
     * 
     * @test
     */
    public function testEntryEndingOutsideEdgeIsRejected(): void
    {
        $validator = new PlacementValidator();

        // Hossza 5, start: 16 -> 16, 17, 18, 19, 20. A 20. cella már kiesik.
        $horizontalCandidate = new Placement(
            id: null,
            clueId: 1,
            answer: 'ALMAS',
            direction: Direction::HORIZONTAL,
            startRow: 0,
            startCol: 16
        );

        $res1 = $validator->validateCandidate([], $horizontalCandidate, 20, 20);

        $this->assertFalse($res1->valid);
        $errorCodes1 = array_column($res1->errors, 'code');
        $this->assertContains(ValidationErrors::OUT_OF_BOUNDS, $errorCodes1);

        $verticalCandidate = new Placement(
            id: null,
            clueId: 2,
            answer: 'ALMAS',
            direction: Direction::VERTICAL,
            startRow: 16,
            startCol: 0
        );

        $res2 = $validator->validateCandidate([], $verticalCandidate, 20, 20);

        $this->assertFalse($res2->valid);
        $errorCodes2 = array_column($res2->errors, 'code');
        $this->assertContains(ValidationErrors::OUT_OF_BOUNDS, $errorCodes2);
    }

    /**
     * Ellenőrzi, hogy ne lehessen egymás mellett elhelyezni két szót, ha azok nem metszik egymást.
     * 
     * @test
     */
    public function testSideAdjacency(): void
    {
        $validator = new PlacementValidator();

        // 1. szó 'TŰZ' vízszintesen, 2. sor, 4-6. oszlop
        $horizontal_placement1 = new Placement(
            id: null,
            clueId: 1,
            answer: 'TŰZ',
            direction: Direction::HORIZONTAL,
            startRow: 2,
            startCol: 4
        );

        // 2. szó 'KUTYA' vízszintesen, 1. sor, 5-9. oszlop (egymás mellett, de nem metszik egymást)
        $horizontal_placement2 = new Placement(
            id: null,
            clueId: 2,
            answer: 'KUTYA',
            direction: Direction::HORIZONTAL,
            startRow: 1,
            startCol: 5
        );


        $result1 = $validator->validateCandidate([$horizontal_placement1], $horizontal_placement2);

        $this->assertFalse($result1->valid);
        $this->assertNotEmpty($result1->errors);
        $errorCodes1 = array_column($result1->errors, 'code');
        $this->assertContains(ValidationErrors::SIDE_ADJACENCY, $errorCodes1);
        $this->assertSame(0, $result1->intersectionCount);

        // 3. szó 'ZEBRA' függőlegesen, 1. sor, 6-10. oszlop
        $vertical_placement1 = new Placement(
            id: null,
            clueId: 3,
            answer: 'ZEBRA',
            direction: Direction::VERTICAL,
            startRow: 1,
            startCol: 6
        );

        // 4. szó 'KUTYA' függőlegesen, 2. sor, 5-9. oszlop (egymás mellett, de nem metszik egymást)
        $vertical_placement2 = new Placement(
            id: null,
            clueId: 4,
            answer: 'KUTYA',
            direction: Direction::VERTICAL,
            startRow: 2,
            startCol: 5
        );

        $result2 = $validator->validateCandidate([$vertical_placement1], $vertical_placement2);

        $this->assertFalse($result2->valid);
        $this->assertNotEmpty($result2->errors);
        $errorCodes2 = array_column($result2->errors, 'code');
        $this->assertContains(ValidationErrors::SIDE_ADJACENCY, $errorCodes2);
        $this->assertSame(0, $result2->intersectionCount);
    }

    /**
     * Ha az elhelyezés egyik végpontja egy másik elhelyezés cellája által blokkolva van, akkor hibát kell dobnia.
     * 
     * @test
     */
    public function testBlockedEndpoint(): void
    {
        $validator = new PlacementValidator();

        // 1. szó 'KIRÁZ' vízszintesen, 1. sor, 3-7. oszlop
        $connecting_placement1 = new Placement(
            id: null,
            clueId: 1,
            answer: 'KIRÁZ',
            direction: Direction::HORIZONTAL,
            startRow: 1,
            startCol: 3
        );

        // 2. szó 'IRT' függőlegesen, 4. oszlop, 1-3. sor
        $connecting_placement2 = new Placement(
            id: null,
            clueId: 2,
            answer: 'IRT',
            direction: Direction::VERTICAL,
            startRow: 1,
            startCol: 4
        );

        // 3. szó 'ZEBRA' függőlegesen, 7. oszlop, 1-5. sor
        $vertical_placement = new Placement(
            id: null,
            clueId: 3,
            answer: 'ZEBRA',
            direction: Direction::VERTICAL,
            startRow: 1,
            startCol: 7
        );

        // 4. szó 'TŰZ' vízszintesen, 3. sor, 4-6. oszlop (a 4. oszlopban blokkolva van a 'IRT' által)
        $blocked_endpoint_placement1 = new Placement(
            id: null,
            clueId: 4,
            answer: 'TŰZ',
            direction: Direction::HORIZONTAL,
            startRow: 3,
            startCol: 4
        );

        $result1 = $validator->validateCandidate([$connecting_placement1, $connecting_placement2, $vertical_placement], $blocked_endpoint_placement1);

        $this->assertFalse($result1->valid);
        $this->assertNotEmpty($result1->errors);
        $errorCodes1 = array_column($result1->errors, 'code');
        $this->assertContains(ValidationErrors::BLOCKED_ENDPOINT, $errorCodes1);
        $this->assertSame(1, $result1->intersectionCount);

        // 5. szó 'LÁB' vízszintesen, 3. sor, 5-7. oszlop (a 7. oszlopban blokkolva van a 'ZEBRA' által)
        $blocked_endpoint_placement2 = new Placement(
            id: null,
            clueId: 5,
            answer: 'LÁB',
            direction: Direction::HORIZONTAL,
            startRow: 3,
            startCol: 5
        );

        $result2 = $validator->validateCandidate([$connecting_placement1, $connecting_placement2, $vertical_placement], $blocked_endpoint_placement2);

        $this->assertFalse($result2->valid);
        $this->assertNotEmpty($result2->errors);
        $errorCodes2 = array_column($result2->errors, 'code');
        $this->assertContains(ValidationErrors::BLOCKED_ENDPOINT, $errorCodes2);
        $this->assertSame(1, $result2->intersectionCount);
    }

    /**
     * Ha már van elhelyezett szó, és a következő elhelyezés nem csatlakozik egy korábbihoz sem, akkor hibát kell dobnia.
     * 
     * @test
     */
    public function testDisconnectedEntry(): void
    {
        $validator = new PlacementValidator();

        // 1. szó 'KIRÁZ' vízszintesen, 1. sor, 3-7. oszlop
        $existing_placement = new Placement(
            id: null,
            clueId: 1,
            answer: 'KIRÁZ',
            direction: Direction::HORIZONTAL,
            startRow: 1,
            startCol: 3
        );

        // 2. szó 'TŰZ' vízszintesen, 5. sor, 4-6. oszlop
        $disconnected_placement = new Placement(
            id: null,
            clueId: 2,
            answer: 'TŰZ',
            direction: Direction::HORIZONTAL,
            startRow: 5,
            startCol: 4
        );

        $result = $validator->validateCandidate([$existing_placement], $disconnected_placement);

        $this->assertFalse($result->valid);
        $this->assertNotEmpty($result->errors);
        $errorCodes = array_column($result->errors, 'code');
        $this->assertContains(ValidationErrors::DISCONNECTED_ENTRY, $errorCodes);
        $this->assertSame(0, $result->intersectionCount);
    }

    /**
     * Több érvényes keresztszóval rendelkező elhelyezés tesztelése.
     *
     * @test
     */ 
    public function testMultipleValidIntersections()
    {
        $validator = new PlacementValidator();

        // 1. szó 'KIRÁZ' vízszintesen, 1. sor, 3-7. oszlop
        $connecting_placement1 = new Placement(
            id: null,
            clueId: 1,
            answer: 'KIRÁZ',
            direction: Direction::HORIZONTAL,
            startRow: 1,
            startCol: 3
        );

        // 2. szó 'IRT' függőlegesen, 4. oszlop, 1-3. sor
        $connecting_placement2 = new Placement(
            id: null,
            clueId: 2,
            answer: 'IRT',
            direction: Direction::VERTICAL,
            startRow: 1,
            startCol: 4
        );

        // 3. szó 'ZEBRA' függőlegesen, 7. oszlop, 1-5. sor
        $connecting_placement3 = new Placement(
            id: null,
            clueId: 3,
            answer: 'ZEBRA',
            direction: Direction::VERTICAL,
            startRow: 1,
            startCol: 7
        );

        // 4. szó 'TRABANT' vízszintesen, 3. sor, 4-10. oszlop
        $placement = new Placement(
            id: null,
            clueId: 4,
            answer: 'TRABANT',
            direction: Direction::HORIZONTAL,
            startRow: 3,
            startCol: 4
        );

        $result = $validator->validateCandidate([$connecting_placement1, $connecting_placement2, $connecting_placement3], $placement);

        $this->assertTrue($result->valid);
        $this->assertEmpty($result->errors);
        $this->assertSame(2, $result->intersectionCount);
    }
}