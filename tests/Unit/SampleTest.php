<?php

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

class SampleTest extends TestCase
{
    public function testBasicAssertion(): void
    {
        $this->assertTrue(true);
        $this->assertSame(2, 1 + 1);
    }
}