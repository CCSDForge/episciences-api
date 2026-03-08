<?php

declare(strict_types=1);

namespace App\Tests\Unit\Exception;

use App\Exception\ResourceNotFoundException;
use PHPUnit\Framework\TestCase;

final class ResourceNotFoundExceptionTest extends TestCase
{
    public function testIsAnException(): void
    {
        $e = new ResourceNotFoundException('not found');
        $this->assertInstanceOf(\Exception::class, $e);
    }

    public function testMessageIsPreserved(): void
    {
        $e = new ResourceNotFoundException('Journal XYZ not found');
        $this->assertSame('Journal XYZ not found', $e->getMessage());
    }

    public function testDefaultMessageIsEmpty(): void
    {
        $e = new ResourceNotFoundException();
        $this->assertSame('', $e->getMessage());
    }

    public function testCanBeCaught(): void
    {
        $caught = false;
        try {
            throw new ResourceNotFoundException('oops');
        } catch (ResourceNotFoundException $e) {
            $caught = true;
            $this->assertSame('oops', $e->getMessage());
        }
        $this->assertTrue($caught);
    }
}
