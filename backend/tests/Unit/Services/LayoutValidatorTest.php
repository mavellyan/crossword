<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Domain\Crossword\Placement;
use App\Enums\Direction;
use App\Services\PlacementValidator;
use App\Enums\ValidationErrors;

class LayoutValidatorTest extends TestCase
{
    /**
     * Ellenőrzi hogy ugyanaz a layout különböző sorrendben is valid marad-e, és ugyanannyi metszéspontot ad-e vissza.
     * Egyúttal azt is leellenőrzi, hogy egy 3 szóból álló layout akkor is helyes, ha a középen lévő
     * összekapcsoló szó az utolsó a sorrendben (jelen esetben az order2 vagy order5).
     * 
     * @test
     */
    public function testCorrectLayoutDifferentOrder(): void
    {
        // HELLO - vízszintesen, 1.sor 0-4. oszlop
        $placement1 = new Placement(
            id: null,
            clueId: 1,
            answer: 'HELLO',
            direction: Direction::HORIZONTAL,
            startRow: 1,
            startCol: 0,
        );

        // WORLD - függőlegesen, 4.oszlop, 0-4. sor, az O betűnél keresztezi a HELLO-t
        $placement2 = new Placement(
            id: null,
            clueId: 2,
            answer: 'WORLD',
            direction: Direction::VERTICAL,
            startRow: 0,
            startCol: 4,
        );

        // DARK - vízszintesen, 4.sor 4-7. oszlop, a D betűnél keresztezi a WORLD-öt
        $placement3 = new Placement(
            id: null,
            clueId: 3,
            answer: 'DARK',
            direction: Direction::HORIZONTAL,
            startRow: 4,
            startCol: 4,
        );

        $order1 = [$placement1, $placement2, $placement3];
        $order2 = [$placement1, $placement3, $placement2];
        $order3 = [$placement2, $placement1, $placement3];
        $order4 = [$placement2, $placement3, $placement1];
        $order5 = [$placement3, $placement1, $placement2];
        $order6 = [$placement3, $placement2, $placement1];

        $validator = new PlacementValidator();

        $result1 = $validator->validateLayout($order1);
        $result2 = $validator->validateLayout($order2);
        $result3 = $validator->validateLayout($order3);
        $result4 = $validator->validateLayout($order4);
        $result5 = $validator->validateLayout($order5);
        $result6 = $validator->validateLayout($order6);

        $this->assertTrue($result1->valid);
        $this->assertTrue($result2->valid);
        $this->assertTrue($result3->valid);
        $this->assertTrue($result4->valid);
        $this->assertTrue($result5->valid);
        $this->assertTrue($result6->valid);

        $this->assertEmpty($result1->errors);
        $this->assertEmpty($result2->errors);
        $this->assertEmpty($result3->errors);
        $this->assertEmpty($result4->errors);
        $this->assertEmpty($result5->errors);
        $this->assertEmpty($result6->errors);

        $this->assertEquals(2, $result1->intersectionCount);
        $this->assertEquals(2, $result2->intersectionCount);
        $this->assertEquals(2, $result3->intersectionCount);
        $this->assertEquals(2, $result4->intersectionCount);
        $this->assertEquals(2, $result5->intersectionCount);
        $this->assertEquals(2, $result6->intersectionCount);
    }

    /**
     * Ellenőrzi hogy a layout validációja felismeri-e a layout összekapcsolatlanságát
     * annak ellenére, hogy van 2 különböző csoport, amelyekben a szavak metszik egymást, de a két csoport között nincs kapcsolat.
     * 
     * @test
     */
    public function testDisconnectedLayout(): void
    {
        // HELLO - vízszintesen, 1.sor 0-4. oszlop, a WORLD elhelyezéssel keresztezi az O betűnél, de a DARK és BAND elhelyezésekkel nincs kapcsolatban
        $placement1 = new Placement(
            id: null,
            clueId: 1,
            answer: 'HELLO',
            direction: Direction::HORIZONTAL,
            startRow: 1,
            startCol: 0,
        );

        // WORLD - függőlegesen, 4.oszlop, 0-4. sor, az O betűnél keresztezi a HELLO-t, de a DARK és BAND elhelyezésekkel nincs kapcsolatban
        $placement2 = new Placement(
            id: null,
            clueId: 2,
            answer: 'WORLD',
            direction: Direction::VERTICAL,
            startRow: 0,
            startCol: 4,
        );

        // DARK - vízszintesen, 6.sor 4-7. oszlop, a BAND metszi az A betűnél, de nincs kapcsolatban a HELLO és WORLD elhelyezésekkel
        $placement3 = new Placement(
            id: null,
            clueId: 3,
            answer: 'DARK',
            direction: Direction::HORIZONTAL,
            startRow: 6,
            startCol: 4,
        );

        // BAND - függőlegesen, 5.oszlop, 5-8. sor, az A betűnél metszi a DARK-ot, de nincs kapcsolatban a HELLO és WORLD elhelyezésekkel
        $placement4 = new Placement(
            id: null,
            clueId: 4,
            answer: 'BAND',
            direction: Direction::VERTICAL,
            startRow: 5,
            startCol: 5,
        );

        $placements = [$placement1, $placement2, $placement3, $placement4];

        $validator = new PlacementValidator();

        $result = $validator->validateLayout($placements);

        $this->assertFalse($result->valid);
        $errorCodes = array_column($result->errors, 'code');
        $this->assertContains(ValidationErrors::DISCONNECTED_LAYOUT, $errorCodes);
        $this->assertEquals(2, $result->intersectionCount);
    }

    /**
     * Ellenőrzi hogy a layout validációja felismeri-e az egyetlen nem kapcsolódó szót a layoutban, annak ellenére, hogy a többi szó metszi egymást.
     *
     * @test
     */
    public function testIsolatedEntry(): void
    {
        // HELLO - vízszintesen, 1.sor 0-4. oszlop
        $placement1 = new Placement(
            id: null,
            clueId: 1,
            answer: 'HELLO',
            direction: Direction::HORIZONTAL,
            startRow: 1,
            startCol: 0,
        );

        // WORLD - függőlegesen, 4.oszlop, 0-4. sor, az O betűnél keresztezi a HELLO-t
        $placement2 = new Placement(
            id: null,
            clueId: 2,
            answer: 'WORLD',
            direction: Direction::VERTICAL,
            startRow: 0,
            startCol: 4,
        );

        // ISOLATED - vízszintesen, 5.sor 0-7. oszlop, nincs kapcsolatban a HELLO és WORLD elhelyezésekkel
        $placement3 = new Placement(
            id: null,
            clueId: 3,
            answer: 'ISOLATED',
            direction: Direction::HORIZONTAL,
            startRow: 6,
            startCol: 0,
        );

        $placements = [$placement1, $placement2, $placement3];

        $validator = new PlacementValidator();

        $result = $validator->validateLayout($placements);

        $this->assertFalse($result->valid);
        $errorCodes = array_column($result->errors, 'code');
        $this->assertContains(ValidationErrors::DISCONNECTED_LAYOUT, $errorCodes);
        $this->assertEquals(1, $result->intersectionCount);
    }

    /**
     * Ellenőrzi hogy a layout validációja felismeri-e ha túl kevés (<2) szó van a layoutban
     *
     * @test
     */
    public function testTooFewEntries(): void
    {
        $placement1 = new Placement(
            id: null,
            clueId: 1,
            answer: 'HELLO',
            direction: Direction::HORIZONTAL,
            startRow: 1,
            startCol: 0,
        );

        $placements = [$placement1];

        $validator = new PlacementValidator();

        $result = $validator->validateLayout($placements);

        $this->assertFalse($result->valid);
        $errorCodes = array_column($result->errors, 'code');
        $this->assertContains(ValidationErrors::TOO_FEW_ENTRIES, $errorCodes);
        $this->assertEquals(0, $result->intersectionCount);
    }

    /**
     * Ellenőrzi hogy a layout validációja felismeri-e a betűütközést, ha két elhelyezés ugyanazon a cellán van, de különböző betűt tartalmaznak.
     * 
     * @test
     */
    public function testConflictingEntries(): void
    {
        // HELLO - vízszintesen, 1.sor 0-4. oszlop
        $placement1 = new Placement(
            id: null,
            clueId: 1,
            answer: 'HELLO',
            direction: Direction::HORIZONTAL,
            startRow: 1,
            startCol: 0,
        );

        // WORLD - függőlegesen, 4.oszlop, 0-4. sor, az O betűnél keresztezi a HELLO-t
        $placement2 = new Placement(
            id: null,
            clueId: 2,
            answer: 'WORLD',
            direction: Direction::VERTICAL,
            startRow: 0,
            startCol: 4,
        );

        // HORSE - vízszintesen, 4.sor 2-6. oszlop, az R betű keresztezi a WORLD 'D' betűjét, ami hibát okoz
        $placement3 = new Placement(
            id: null,
            clueId: 3,
            answer: 'HORSE',
            direction: Direction::HORIZONTAL,
            startRow: 4,
            startCol: 2,
        );

        $order1 = [$placement1, $placement2, $placement3];
        $order2 = [$placement1, $placement3, $placement2];
        $order3 = [$placement2, $placement1, $placement3];
        $order4 = [$placement2, $placement3, $placement1];
        $order5 = [$placement3, $placement1, $placement2];
        $order6 = [$placement3, $placement2, $placement1];

        $validator = new PlacementValidator();

        $result1 = $validator->validateLayout($order1);
        $result2 = $validator->validateLayout($order2);
        $result3 = $validator->validateLayout($order3);
        $result4 = $validator->validateLayout($order4);
        $result5 = $validator->validateLayout($order5);
        $result6 = $validator->validateLayout($order6);

        $this->assertFalse($result1->valid);
        $this->assertFalse($result2->valid);
        $this->assertFalse($result3->valid);
        $this->assertFalse($result4->valid);
        $this->assertFalse($result5->valid);
        $this->assertFalse($result6->valid);

        $errorCodes1 = array_column($result1->errors, 'code');
        $this->assertContains(ValidationErrors::LETTER_CONFLICT, $errorCodes1);
        $errorCodes2 = array_column($result2->errors, 'code');
        $this->assertContains(ValidationErrors::LETTER_CONFLICT, $errorCodes2);
        $errorCodes3 = array_column($result3->errors, 'code');
        $this->assertContains(ValidationErrors::LETTER_CONFLICT, $errorCodes3);
        $errorCodes4 = array_column($result4->errors, 'code');
        $this->assertContains(ValidationErrors::LETTER_CONFLICT, $errorCodes4);
        $errorCodes5 = array_column($result5->errors, 'code');
        $this->assertContains(ValidationErrors::LETTER_CONFLICT, $errorCodes5);
        $errorCodes6 = array_column($result6->errors, 'code');
        $this->assertContains(ValidationErrors::LETTER_CONFLICT, $errorCodes6);

        $this->assertEquals(1, $result1->intersectionCount);
        $this->assertEquals(1, $result2->intersectionCount);
        $this->assertEquals(1, $result3->intersectionCount);
        $this->assertEquals(1, $result4->intersectionCount);
        $this->assertEquals(1, $result5->intersectionCount);
        $this->assertEquals(1, $result6->intersectionCount);
    }
}
