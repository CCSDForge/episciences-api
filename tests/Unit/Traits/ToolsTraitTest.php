<?php

namespace App\Tests\Unit\Traits;

use App\Traits\ToolsTrait;
use LengthException;
use PHPUnit\Framework\TestCase;

class ToolsTraitTest extends TestCase
{
    use ToolsTrait;

    public function testApplyFilterByWithValidFilter(): void
    {
        $result = [
            ['id' => 1, 'status' => 'active', 'name' => 'John'],
            ['id' => 2, 'status' => 'inactive', 'name' => 'Jane'],
            ['id' => 3, 'status' => 'active', 'name' => 'Bob'],
        ];

        $filtered = $this->applyFilterBy($result, 'status', 'active');

        $expected = [
            'active' => [
                ['id' => 1, 'name' => 'John'],
                ['id' => 3, 'name' => 'Bob'],
            ]
        ];

        $this->assertEquals($expected, $filtered);
    }

    public function testApplyFilterByWithNoMatchingFilter(): void
    {
        $result = [
            ['id' => 1, 'status' => 'active'],
            ['id' => 2, 'status' => 'inactive'],
        ];

        $filtered = $this->applyFilterBy($result, 'status', 'pending');

        $this->assertEquals([], $filtered);
    }

    public function testApplyFilterByWithNullParameters(): void
    {
        $result = [['id' => 1, 'status' => 'active']];

        $filtered = $this->applyFilterBy($result, null, 'active');
        $this->assertEquals($result, $filtered);

        $filtered = $this->applyFilterBy($result, 'status', null);
        $this->assertEquals($result, $filtered);
    }

    public function testApplyFilterByWithExistingKey(): void
    {
        $result = ['status' => 'active'];

        $filtered = $this->applyFilterBy($result, 'status', 'active');

        $this->assertEquals($result, $filtered);
    }

    public function testCheckArrayEqualityWithEqualArrays(): void
    {
        $tab1 = [1, 2, 3, 4];
        $tab2 = [1, 2, 3, 4];

        $result = $this->checkArrayEquality($tab1, $tab2);

        $expected = ['equality' => true, 'arrayDiff' => []];
        $this->assertEquals($expected, $result);
    }

    public function testCheckArrayEqualityWithDifferentArrays(): void
    {
        $tab1 = [1, 2, 3, 5];
        $tab2 = [1, 2, 4, 6];

        $result = $this->checkArrayEquality($tab1, $tab2);

        $this->assertFalse($result['equality']);
        // The function preserves array keys, so we need to check the actual values
        $this->assertEquals([2 => 3, 3 => 5], $result['arrayDiff']['in']);
        $this->assertEquals([2 => 4, 3 => 6], $result['arrayDiff']['out']);
    }

    public function testCheckArrayEqualityWithEmptyArrays(): void
    {
        $result = $this->checkArrayEquality([], []);

        $expected = ['equality' => true, 'arrayDiff' => []];
        $this->assertEquals($expected, $result);
    }

    public function testIsValidDateWithValidDates(): void
    {
        $this->assertTrue($this->isValidDate('2023-12-25'));
        $this->assertTrue($this->isValidDate('2023-02-28'));
        $this->assertTrue($this->isValidDate('2024-02-29')); // Leap year
        $this->assertTrue($this->isValidDate('25/12/2023', 'd/m/Y'));
    }

    public function testIsValidDateWithInvalidDates(): void
    {
        $this->assertFalse($this->isValidDate('2023-13-01')); // Invalid month
        $this->assertFalse($this->isValidDate('2023-02-30')); // Invalid day
        $this->assertFalse($this->isValidDate('invalid-date'));
        $this->assertFalse($this->isValidDate('2023/12/25')); // Wrong format
        $this->assertFalse($this->isValidDate('2023-02-29')); // Non-leap year
    }

    public function testConvertToCamelCaseBasic(): void
    {
        $this->assertEquals('firstName', $this->convertToCamelCase('first_name'));
        $this->assertEquals('userId', $this->convertToCamelCase('user_id'));
        $this->assertEquals('simpleString', $this->convertToCamelCase('simple_string'));
    }

    public function testConvertToCamelCaseWithCapitalizeFirst(): void
    {
        $this->assertEquals('FirstName', $this->convertToCamelCase('first_name', '_', true));
        $this->assertEquals('UserId', $this->convertToCamelCase('user_id', '_', true));
    }

    public function testConvertToCamelCaseWithCustomSeparator(): void
    {
        $this->assertEquals('firstName', $this->convertToCamelCase('first-name', '-'));
        $this->assertEquals('userProfile', $this->convertToCamelCase('user.profile', '.'));
    }

    public function testConvertToCamelCaseWithUppercaseInput(): void
    {
        $this->assertEquals('firstName', $this->convertToCamelCase('FIRST_NAME'));
        $this->assertEquals('userId', $this->convertToCamelCase('USER_ID'));
    }

    public function testConvertToCamelCaseWithMixedCase(): void
    {
        $this->assertEquals('firstName', $this->convertToCamelCase('First_Name'));
        $this->assertEquals('userId', $this->convertToCamelCase('User_Id'));
    }

    public function testIsInUppercaseWithUppercaseStrings(): void
    {
        $this->assertTrue($this->isInUppercase('FIRST_NAME'));
        $this->assertTrue($this->isInUppercase('USER_ID'));
        $this->assertTrue($this->isInUppercase('SIMPLE'));
        $this->assertTrue($this->isInUppercase('A_B_C_D'));
    }

    public function testIsInUppercaseWithLowercaseStrings(): void
    {
        $this->assertFalse($this->isInUppercase('first_name'));
        $this->assertFalse($this->isInUppercase('user_id'));
        $this->assertFalse($this->isInUppercase('simple'));
    }

    public function testIsInUppercaseWithMixedCase(): void
    {
        $this->assertFalse($this->isInUppercase('first_NAME')); // has lowercase at start
        $this->assertFalse($this->isInUppercase('USER_id')); // has lowercase at end
        $this->assertTrue($this->isInUppercase('Mixed_Case_STRING')); // no ctype_lower parts, ends uppercase
    }

    public function testIsInUppercaseWithCustomSeparator(): void
    {
        $this->assertTrue($this->isInUppercase('FIRST-NAME', '-'));
        $this->assertFalse($this->isInUppercase('first-name', '-'));
        $this->assertTrue($this->isInUppercase('USER.PROFILE', '.'));
    }

    public function testGetMedianWithOddNumberOfElements(): void
    {
        $array = [1, 3, 5, 7, 9];
        $this->assertEquals(5, $this->getMedian($array));

        $array = [10, 20, 30];
        $this->assertEquals(20, $this->getMedian($array));
    }

    public function testGetMedianWithEvenNumberOfElements(): void
    {
        $array = [1, 2, 3, 4];
        $this->assertEquals(3, $this->getMedian($array)); // (2+3)/2 = 2.5 rounded to 3

        $array = [10, 20, 30, 40];
        $this->assertEquals(25, $this->getMedian($array)); // (20+30)/2 = 25
    }

    public function testGetMedianWithUnsortedArray(): void
    {
        $array = [9, 1, 5, 3, 7];
        $this->assertEquals(5, $this->getMedian($array));

        $array = [40, 10, 30, 20];
        $this->assertEquals(25, $this->getMedian($array));
    }

    public function testGetMedianWithSingleElement(): void
    {
        $array = [42];
        $this->assertEquals(42, $this->getMedian($array));
    }

    public function testGetMedianWithEmptyArrayThrowsException(): void
    {
        $this->expectException(LengthException::class);
        $this->expectExceptionMessage('Cannot calculate median because Argument #1 ($array) is empty');

        $this->getMedian([]);
    }

    public function testGetMedianWithFloatingPointNumbers(): void
    {
        $array = [1.5, 2.7, 3.1, 4.9];
        $this->assertEquals(3, $this->getMedian($array)); // (2.7+3.1)/2 = 2.9 rounded to 3

        $array = [1.1, 2.2, 3.3];
        $this->assertEquals(2.2, $this->getMedian($array));
    }
}