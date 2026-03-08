<?php

declare(strict_types=1);

namespace App\Tests\Unit\Resource;

use App\Resource\Range;
use App\Resource\RangeType;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Range and RangeType resources.
 */
final class RangeTest extends TestCase
{
    // ── Range ─────────────────────────────────────────────────────────────────

    public function testDefaultNameIsRange(): void
    {
        $range = new Range();
        $this->assertSame('range', $range->getName());
    }

    public function testDefaultYearsIsEmptyArray(): void
    {
        $range = new Range();
        $this->assertSame([], $range->getYears());
    }

    public function testSetYearsFluentInterface(): void
    {
        $range = new Range();
        $result = $range->setYears([2022, 2023]);
        $this->assertSame($range, $result);
        $this->assertSame([2022, 2023], $range->getYears());
    }

    public function testSetYearsDefaultIsEmptyArray(): void
    {
        $range = new Range();
        $range->setYears([2020, 2021]);
        $range->setYears(); // call with default
        $this->assertSame([], $range->getYears());
    }

    public function testSetAndGetYearsWithMultipleValues(): void
    {
        $range = new Range();
        $years = [2020, 2021, 2022, 2023];
        $range->setYears($years);
        $this->assertCount(4, $range->getYears());
        $this->assertSame($years, $range->getYears());
    }

    // ── RangeType ─────────────────────────────────────────────────────────────

    public function testRangeTypeDefaultNameIsRangeType(): void
    {
        $rangeType = new RangeType();
        $this->assertSame('range-type', $rangeType->getName());
    }

    public function testRangeTypeDefaultTypesIsEmptyArray(): void
    {
        $rangeType = new RangeType();
        $this->assertSame([], $rangeType->getTypes());
    }

    public function testRangeTypeSetTypesFluentInterface(): void
    {
        $rangeType = new RangeType();
        $types = ['regular', 'special'];
        $result = $rangeType->setTypes($types);
        $this->assertSame($rangeType, $result);
        $this->assertSame($types, $rangeType->getTypes());
    }

    public function testRangeTypeInheritsGetYearsFromRange(): void
    {
        $rangeType = new RangeType();
        $rangeType->setYears([2023]);
        $this->assertSame([2023], $rangeType->getYears());
    }

    public function testRangeTypeCanSetBothYearsAndTypes(): void
    {
        $rangeType = new RangeType();
        $rangeType->setYears([2022, 2023])->setTypes(['regular', 'proceedings']);
        $this->assertSame([2022, 2023], $rangeType->getYears());
        $this->assertSame(['regular', 'proceedings'], $rangeType->getTypes());
    }

    public function testRangeTypeIsSubclassOfRange(): void
    {
        $this->assertInstanceOf(Range::class, new RangeType());
    }
}
