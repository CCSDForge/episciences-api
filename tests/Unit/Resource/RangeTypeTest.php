<?php

declare(strict_types=1);

namespace App\Tests\Unit\Resource;

use App\Resource\Range;
use App\Resource\RangeType;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for RangeType resource.
 *
 * RangeType extends Range and adds a $types array with getTypes/setTypes.
 */
final class RangeTypeTest extends TestCase
{
    private RangeType $rangeType;

    protected function setUp(): void
    {
        $this->rangeType = new RangeType();
    }

    public function testExtendsRange(): void
    {
        $this->assertInstanceOf(Range::class, $this->rangeType);
    }

    public function testDefaultNameIsRangeType(): void
    {
        $this->assertSame('range-type', $this->rangeType->getName());
    }

    public function testDefaultTypesIsEmptyArray(): void
    {
        $this->assertSame([], $this->rangeType->getTypes());
    }

    public function testDefaultYearsIsEmptyArray(): void
    {
        $this->assertSame([], $this->rangeType->getYears());
    }

    public function testSetTypesReturnsSelf(): void
    {
        $result = $this->rangeType->setTypes(['journal', 'special']);
        $this->assertSame($this->rangeType, $result);
    }

    public function testGetTypesReturnsSetValue(): void
    {
        $this->rangeType->setTypes(['proceedings', 'thematic']);
        $this->assertSame(['proceedings', 'thematic'], $this->rangeType->getTypes());
    }

    public function testSetTypesWithEmptyArrayResetsToEmpty(): void
    {
        $this->rangeType->setTypes(['a', 'b']);
        $this->rangeType->setTypes([]);
        $this->assertSame([], $this->rangeType->getTypes());
    }

    public function testSetYearsReturnsSelf(): void
    {
        $result = $this->rangeType->setYears([2020, 2021, 2022]);
        $this->assertSame($this->rangeType, $result);
    }

    public function testGetYearsReturnsSetValue(): void
    {
        $this->rangeType->setYears([2023, 2024]);
        $this->assertSame([2023, 2024], $this->rangeType->getYears());
    }

    public function testRangeTypeAndTypesBothSetable(): void
    {
        $this->rangeType->setYears([2020])->setTypes(['special']);
        $this->assertSame([2020], $this->rangeType->getYears());
        $this->assertSame(['special'], $this->rangeType->getTypes());
    }
}
