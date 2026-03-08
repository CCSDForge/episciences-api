<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\Entity\Review;
use App\Repository\ReviewRepository;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ReviewRepository::getJournalByIdentifier().
 *
 * The method builds a criteria array from the identifier type (int → rvid, string → code)
 * and optionally adds STATUS_ENABLED when $strict=true.
 * We use a partial mock to intercept findOneBy() and capture the criteria passed.
 *
 * Covers:
 * - int identifier → criteria uses 'rvid'
 * - string identifier → criteria uses 'code'
 * - $strict=true (default) → criteria includes status=STATUS_ENABLED
 * - $strict=false → criteria does NOT include status
 * - null identifier → treated as string null → criteria uses 'code' => null
 * - Return value is forwarded from findOneBy()
 */
final class ReviewRepositoryTest extends TestCase
{
    /**
     * Build a partial mock that captures what criteria is passed to findOneBy().
     * The mock always returns $returnValue from findOneBy().
     */
    private function makeRepository(?Review $returnValue, array &$capturedCriteria): ReviewRepository
    {
        $repo = $this->getMockBuilder(ReviewRepository::class)
            ->setConstructorArgs([$this->createStub(ManagerRegistry::class)])
            ->onlyMethods(['findOneBy'])
            ->getMock();

        $repo->method('findOneBy')
            ->willReturnCallback(function (array $criteria) use ($returnValue, &$capturedCriteria): ?Review {
                $capturedCriteria = $criteria;
                return $returnValue;
            });

        return $repo;
    }

    // ── int identifier ────────────────────────────────────────────────────────

    public function testIntIdentifierUsesRvid(): void
    {
        $criteria = [];
        $repo = $this->makeRepository(null, $criteria);
        $repo->getJournalByIdentifier(42);

        $this->assertArrayHasKey('rvid', $criteria);
        $this->assertSame(42, $criteria['rvid']);
        $this->assertArrayNotHasKey('code', $criteria);
    }

    public function testIntIdentifierWithStrictTrue(): void
    {
        $criteria = [];
        $repo = $this->makeRepository(null, $criteria);
        $repo->getJournalByIdentifier(10, true);

        $this->assertArrayHasKey('status', $criteria);
        $this->assertSame(Review::STATUS_ENABLED, $criteria['status']);
    }

    public function testIntIdentifierWithStrictFalseOmitsStatus(): void
    {
        $criteria = [];
        $repo = $this->makeRepository(null, $criteria);
        $repo->getJournalByIdentifier(10, false);

        $this->assertArrayNotHasKey('status', $criteria);
    }

    // ── string identifier ─────────────────────────────────────────────────────

    public function testStringIdentifierUsesCode(): void
    {
        $criteria = [];
        $repo = $this->makeRepository(null, $criteria);
        $repo->getJournalByIdentifier('epijinfo');

        $this->assertArrayHasKey('code', $criteria);
        $this->assertSame('epijinfo', $criteria['code']);
        $this->assertArrayNotHasKey('rvid', $criteria);
    }

    public function testStringIdentifierWithStrictTrue(): void
    {
        $criteria = [];
        $repo = $this->makeRepository(null, $criteria);
        $repo->getJournalByIdentifier('myjournal', true);

        $this->assertArrayHasKey('status', $criteria);
        $this->assertSame(Review::STATUS_ENABLED, $criteria['status']);
    }

    public function testStringIdentifierWithStrictFalseOmitsStatus(): void
    {
        $criteria = [];
        $repo = $this->makeRepository(null, $criteria);
        $repo->getJournalByIdentifier('myjournal', false);

        $this->assertArrayNotHasKey('status', $criteria);
    }

    // ── default parameter ($strict = true) ────────────────────────────────────

    public function testDefaultStrictIsTrue(): void
    {
        $criteria = [];
        $repo = $this->makeRepository(null, $criteria);
        $repo->getJournalByIdentifier('epijinfo'); // no second arg

        $this->assertArrayHasKey('status', $criteria);
        $this->assertSame(Review::STATUS_ENABLED, $criteria['status']);
    }

    // ── return value ──────────────────────────────────────────────────────────

    public function testReturnsNullWhenNotFound(): void
    {
        $criteria = [];
        $repo = $this->makeRepository(null, $criteria);
        $result = $repo->getJournalByIdentifier('not-existing');

        $this->assertNull($result);
    }

    public function testReturnsReviewWhenFound(): void
    {
        $review = $this->createStub(Review::class);
        $criteria = [];
        $repo = $this->makeRepository($review, $criteria);
        $result = $repo->getJournalByIdentifier('epijinfo');

        $this->assertSame($review, $result);
    }

    // ── null identifier → treated as string ───────────────────────────────────

    public function testNullIdentifierUsesCode(): void
    {
        $criteria = [];
        $repo = $this->makeRepository(null, $criteria);
        $repo->getJournalByIdentifier(null);

        // null is not int → goes to the 'code' branch
        $this->assertArrayHasKey('code', $criteria);
        $this->assertArrayNotHasKey('rvid', $criteria);
    }
}
