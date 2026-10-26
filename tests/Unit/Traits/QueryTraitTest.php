<?php

namespace App\Tests\Unit\Traits;

use App\Traits\QueryTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class QueryTraitTest extends TestCase
{
    use QueryTrait;

    public function testToValidYearsConvertsToIntegers(): void
    {
        $this->assertSame([2023], self::toValidYears('2023'));
        $this->assertSame([2023], self::toValidYears(2023));
        $this->assertSame([2023, 2024], self::toValidYears(['2023', 2024, '2023']));
        $this->assertSame([], self::toValidYears([]));
    }

    /**
     * @param array<mixed>|string|int $years
     */
    #[DataProvider('invalidYearsProvider')]
    public function testToValidYearsRejectsInvalidValues(array|string|int $years): void
    {
        $this->assertNull(self::toValidYears($years));
    }

    /**
     * @return iterable<string, array{array<mixed>|string|int}>
     */
    public static function invalidYearsProvider(): iterable
    {
        yield 'not a year' => ['abc'];
        yield 'incomplete year' => ['24'];
        yield 'negative year' => [-2023];
        yield 'year with spaces' => [' 2023'];
        yield 'one invalid year among several' => [['2023', 'abc']];
        yield 'nested array' => [[['2023']]];
        yield 'float' => [[2023.0]];
    }

    public function testWhereYearsAddsTheValidYears(): void
    {
        $sql = 'SELECT 1 FROM t WHERE 1';
        $this->whereYears($sql, ['2023', 2024], 't.date');

        $this->assertSame('SELECT 1 FROM t WHERE 1 AND YEAR(t.date) IN (2023,2024)', $sql);
    }

    public function testWhereYearsGivesNoResultForAnInvalidYear(): void
    {
        $sql = 'SELECT 1 FROM t WHERE 1';
        $this->whereYears($sql, ['2023', 'abc'], 't.date');

        $this->assertSame('SELECT 1 FROM t WHERE 1 AND 1 = 0', $sql);
    }

    public function testWhereYearsIgnoresAnEmptyValue(): void
    {
        foreach ([null, '', '0', 0, []] as $years) {
            $sql = 'SELECT 1 FROM t WHERE 1';
            $this->whereYears($sql, $years);

            $this->assertSame('SELECT 1 FROM t WHERE 1', $sql);
        }
    }
}
