<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\Repository\RefreshTokenRepository;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for RefreshTokenRepository.
 *
 * Covers:
 * - findInvalid(null) returns [] immediately (no DB query needed)
 * - The findInvalid() conditional path can be exercised with a partial mock
 */
final class RefreshTokenRepositoryTest extends TestCase
{
    private function makeRepository(): RefreshTokenRepository
    {
        return $this->getMockBuilder(RefreshTokenRepository::class)
            ->setConstructorArgs([$this->createStub(ManagerRegistry::class)])
            ->onlyMethods(['findBy'])
            ->getMock();
    }

    public function testFindInvalidWithNullDatetimeReturnsEmptyArray(): void
    {
        $repo = $this->makeRepository();

        // findBy should never be called when $datetime is null
        $repo->expects($this->never())->method('findBy');

        $result = $repo->findInvalid(null);

        $this->assertSame([], $result);
    }

    public function testFindInvalidWithNullDatetimeDefaultParameterReturnsEmptyArray(): void
    {
        $repo = $this->makeRepository();

        // $datetime defaults to null → same as passing null explicitly
        $result = $repo->findInvalid();

        $this->assertSame([], $result);
    }

    public function testFindInvalidWithDatetimeDelegatesToFindBy(): void
    {
        $repo = $this->makeRepository();
        $datetime = new \DateTime('2024-01-01');

        $token = $this->createStub(\App\Entity\RefreshToken::class);
        $repo->method('findBy')
            ->with(['valid' => $datetime])
            ->willReturn([$token]);

        $result = $repo->findInvalid($datetime);

        $this->assertCount(1, $result);
        $this->assertSame($token, $result[0]);
    }

    public function testFindInvalidWithDatetimeReturnsEmptyArrayWhenNoneFound(): void
    {
        $repo = $this->makeRepository();
        $datetime = new \DateTime('2050-12-31');

        $repo->method('findBy')->willReturn([]);

        $result = $repo->findInvalid($datetime);

        $this->assertSame([], $result);
    }
}
