<?php

namespace Tests\Unit\Services;

use App\Domain\Crossword\Placement;
use App\Domain\Crossword\PlacementValidationResult;
use App\Enums\Direction;
use App\Enums\ValidationErrors;
use App\Services\PlacementValidator;
use PHPUnit\Framework\TestCase;

class GuidedLayoutValidatorTest extends TestCase
{
    private PlacementValidator $validator;
    private int $nextClueId = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new PlacementValidator();
        $this->nextClueId = 1;
    }

    private function place(string $answer, int $row, int $col, Direction $direction = Direction::HORIZONTAL): Placement
    {
        return new Placement(
            id: null,
            clueId: $this->nextClueId++,
            answer: $answer,
            direction: $direction,
            startRow: $row,
            startCol: $col,
        );
    }

    /**
     * A fő megoldás "TŰZ", a 3. oszlopban van, a következő elrendezésben:
     *   0.sor: KÖTÉL, 1. oszlopban kezdődik, a T a 3. oszlopban van, az első betűje a főmegoldásnak
     *   1.sor: HŰTÉS, 2. oszlopban kezdődik, az Ű a 3. oszlopban van, a második betűje a főmegoldásnak
     *   2.sor: MÉZ, 1. oszlopban kezdődik, a Z a 3. oszlopban van, a harmadik betűje a főmegoldásnak
     *
     * @return Placement[]
     */
    private function validLayout(): array
    {
        return [
            $this->place('KÖTÉL', 0, 1),
            $this->place('HŰTÉS', 1, 2),
            $this->place('MÉZ', 2, 1),
        ];
    }

    private function codes(PlacementValidationResult $result): array
    {
        return array_column($result->errors, 'code');
    }

    /**
     * A fő megoldás jól elhelyezett, a 3. oszlopban van.
     *
     * @test
     */
    public function testValidAlignedMainSolution(): void
    {
        $result = $this->validator->validateGuidedLayout($this->validLayout(), 'TŰZ');

        $this->assertTrue($result->valid);
        $this->assertEmpty($result->errors);
        $this->assertSame(3, $result->intersectionCount);
    }

    /**
     * Ha a sorok nincsenek sorrendben, a validáció akkor is sikeres, ha a főmegoldás elhelyezése helyes.
     *
     * @test
     */
    public function testPlacementsSuppliedOutOfOrderAreAccepted(): void
    {
        [$row0, $row1, $row2] = $this->validLayout();

        $result = $this->validator->validateGuidedLayout([$row2, $row0, $row1], 'TŰZ');

        $this->assertTrue($result->valid);
        $this->assertEmpty($result->errors);
    }

    /**
     * A fő megoldás és a válaszok kis- és nagybetűkkel is megadhatók, a validáció nem különbözteti meg.
     *
     * @test
     */
    public function testMainSolutionAndAnswersAreCaseInsensitive(): void
    {
        $placements = [
            $this->place('kötél', 0, 1),
            $this->place('hűtés', 1, 2),
            $this->place('méz', 2, 1),
        ];

        $result = $this->validator->validateGuidedLayout($placements, 'tűz');

        $this->assertTrue($result->valid, json_encode($result->errors));
    }

    /**
     * A magyar ékezetes betűk különbözők, és a validáció nem kezeli őket ugyanúgy, hibát okoznak.
     *
     * @test
     */
    public function testUDoubleAcuteAndUDiaeresisAreDifferentLetters(): void
    {
        // HÜTÉS rövid Ü betűvel, de a főmegoldásban Ű betű van
        $placements = [
            $this->place('KÖTÉL', 0, 1),
            $this->place('HÜTÉS', 1, 2),
            $this->place('MÉZ', 2, 1),
        ];

        $result = $this->validator->validateGuidedLayout($placements, 'TŰZ');

        $this->assertFalse($result->valid);
        $this->assertContains(ValidationErrors::MAIN_SOLUTION_MISMATCH, $this->codes($result));
        $this->assertSame(1, $result->errors[0]['row']);
    }

    /**
     * Ha a bemeneti adatok száma nem megfelelő, a validáció hibát jelez.
     *
     * @test
     */
    public function testWrongEntryCountIsRejected(): void
    {
        [$row0, $row1, $row2] = $this->validLayout();

        $tooFew = $this->validator->validateGuidedLayout([$row0, $row1], 'TŰZ');

        $this->assertFalse($tooFew->valid);
        $this->assertContains(ValidationErrors::MAIN_SOLUTION_MISMATCH, $this->codes($tooFew));
        $this->assertSame(0, $tooFew->intersectionCount);

        $tooMany = $this->validator->validateGuidedLayout(
            [$row0, $row1, $row2, $this->place('ALMA', 3, 0)],
            'TŰZ'
        );

        $this->assertFalse($tooMany->valid);
        $this->assertContains(ValidationErrors::MAIN_SOLUTION_MISMATCH, $this->codes($tooMany));
    }

    /**
     * Ha a fő megoldásban lévő betű hiányzik, a validáció hibát jelez, és a hibát a megfelelő sorban jelzi.
     *
     * @test
     */
    public function testMissingMainLetterIsReportedOnTheRightRow(): void
    {
        // A 2. sorban kellene lennie a Z betűnek, de a MÉL nem tartalmazza
        $placements = [
            $this->place('KÖTÉL', 0, 1),
            $this->place('HŰTÉS', 1, 2),
            $this->place('MÉL', 2, 1),
        ];

        $result = $this->validator->validateGuidedLayout($placements, 'TŰZ');

        $this->assertFalse($result->valid);
        $this->assertContains(ValidationErrors::MAIN_SOLUTION_MISMATCH, $this->codes($result));
        $this->assertSame(2, $result->errors[0]['row']);
    }

    /**
     * Ha a fő megoldásban lévő betűk nem ugyanabban az oszlopban vannak, a validáció hibát jelez.
     *
     * @test
     */
    public function testDifferentMainColumnsAreRejected(): void
    {
        // A 'MÉZ' egy oszloppal balra van eltolva, így a Z betű a 2. oszlopba kerül, nem a 3.-ba
        $placements = [
            $this->place('KÖTÉL', 0, 1),
            $this->place('HŰTÉS', 1, 2),
            $this->place('MÉZ', 2, 0),
        ];

        $result = $this->validator->validateGuidedLayout($placements, 'TŰZ');

        $this->assertFalse($result->valid);
        $this->assertContains(ValidationErrors::GUIDED_LAYOUT_MAIN_COLUMN_MISMATCH, $this->codes($result));
    }

    /**
     * Ha a bemeneti adatok függőleges irányban vannak elhelyezve, a validáció hibát jelez.
     *
     * @test
     */
    public function testVerticalPlacementIsRejected(): void
    {
        $placements = [
            $this->place('KÖTÉL', 0, 1),
            $this->place('HŰTÉS', 1, 2, Direction::VERTICAL),
            $this->place('MÉZ', 2, 1),
        ];

        $result = $this->validator->validateGuidedLayout($placements, 'TŰZ');

        $this->assertFalse($result->valid);
        $this->assertContains(ValidationErrors::GUIDED_LAYOUT_DIRECTION_MISMATCH, $this->codes($result));
    }

    /**
     * Ha a bemeneti adatok nem folyamatos sorokban kezdődnek, a validáció hibát jelez.
     *
     * @test
     */
    public function testPlacementsNotStartingOnConsecutiveRowsAreRejected(): void
    {
        // 1-2-3 sorok a 0-1-2 sorok helyett
        $placements = [
            $this->place('KÖTÉL', 1, 1),
            $this->place('HŰTÉS', 2, 2),
            $this->place('MÉZ', 3, 1),
        ];

        $result = $this->validator->validateGuidedLayout($placements, 'TŰZ');

        $this->assertFalse($result->valid);
        $this->assertContains(ValidationErrors::GUIDED_LAYOUT_POSITION_MISMATCH, $this->codes($result));
    }

    /**
     * Ha a szó túl hosszú a jobb szélen, a validáció hibát jelez.
     *
     * @test
     */
    public function testWordOverflowingTheRightEdgeIsRejected(): void
    {
        // A 18. oszlopban van a főmegoldás, de a KÖTÉL (16..20) és HŰTÉS (17..21) nem fér el a 20 oszlopban.
        $placements = [
            $this->place('KÖTÉL', 0, 16),
            $this->place('HŰTÉS', 1, 17),
            $this->place('MÉZ', 2, 16),
        ];

        $result = $this->validator->validateGuidedLayout($placements, 'TŰZ');

        $this->assertFalse($result->valid);
        $this->assertContains(ValidationErrors::OUT_OF_BOUNDS, $this->codes($result));
    }

    /**
     * Ha a szó pontosan az utolsó oszlopban végződik, a validáció sikeres.
     *
     * @test */
    public function testWordEndingExactlyOnTheLastColumnIsValid(): void
    {
        // A 'HŰTÉS' a 15..19 oszlopban van, ami a 20 oszlopos rácsban még éppen elfér. A fő oszlop = 16.
        $placements = [
            $this->place('KÖTÉL', 0, 14),
            $this->place('HŰTÉS', 1, 15),
            $this->place('MÉZ', 2, 14),
        ];

        $result = $this->validator->validateGuidedLayout($placements, 'TŰZ');

        $this->assertTrue($result->valid, json_encode($result->errors));
    }

    /**
     * Ha a kezdőoszlop negatív, a validáció hibát jelez.
     *
     * @test
     */
    public function testNegativeStartColumnIsRejected(): void
    {
        $placements = [
            $this->place('KÖTÉL', 0, -1),
            $this->place('HŰTÉS', 1, 0),
            $this->place('MÉZ', 2, -1),
        ];

        $result = $this->validator->validateGuidedLayout($placements, 'TŰZ');

        $this->assertFalse($result->valid);
        $this->assertContains(ValidationErrors::OUT_OF_BOUNDS, $this->codes($result));
    }

    /**
     * Ha a főmegoldás túl magas a maximális sorszámhoz képest, hibát jelez a validáció.
     *
     * @test
     */
    public function testRowBeyondMaximumRowsIsRejected(): void
    {
        $result = $this->validator->validateGuidedLayout($this->validLayout(), 'TŰZ', maximumRows: 2);

        $this->assertFalse($result->valid);
        $this->assertContains(ValidationErrors::OUT_OF_BOUNDS, $this->codes($result));
    }
}