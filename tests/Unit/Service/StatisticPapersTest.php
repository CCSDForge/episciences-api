<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Paper;
use App\Exception\ResourceNotFoundException;
use App\Repository\PapersRepository;
use App\Service\StatisticPapers;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class StatisticPapersTest extends TestCase
{
    #[DataProvider('withPapersProvider')]
    public function testWithPapersIsABoolean(mixed $value, bool $expected): void
    {
        $this->assertSame($expected, StatisticPapers::isRequested($value));
    }

    public static function withPapersProvider(): iterable
    {
        yield 'absent' => [null, false];
        yield 'empty' => ['', false];
        yield 'true' => ['true', true];
        yield '1' => ['1', true];
        yield 'false' => ['false', false];
        yield '0' => ['0', false];
    }

    #[DataProvider('invalidWithPapersProvider')]
    public function testInvalidWithPapersIsRejected(mixed $value): void
    {
        $this->expectException(ResourceNotFoundException::class);

        StatisticPapers::isRequested($value);
    }

    public static function invalidWithPapersProvider(): iterable
    {
        yield 'not a boolean' => ['abc'];
        yield 'array' => [['true']];
    }

    /**
     * One DOCID by paper (its latest version), sorted; the conflicted papers are not listed, only counted.
     */
    public function testPapersAreListedByDocIdWithoutTheConflictedOnes(): void
    {
        $papersRepository = $this->createMock(PapersRepository::class);
        // a single query for all the figures, without the conflicted paper 3
        $papersRepository->expects($this->once())->method('getLatestDocIds')->with([5, 1, 2])->willReturn([5 => 50, 1 => 12, 2 => 20]);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturnMap([[Paper::class, $papersRepository]]);

        $service = new StatisticPapers($entityManager, $this->createStub(Security::class));

        [$papers, $hidden] = $service->toDocIds(['accepted' => [5, 3, 1], 'refused' => [2]], [3 => true]);

        $this->assertSame(['accepted' => [12, 50], 'refused' => [20]], $papers);
        $this->assertSame(['accepted' => 1, 'refused' => 0], $hidden);
    }
}
