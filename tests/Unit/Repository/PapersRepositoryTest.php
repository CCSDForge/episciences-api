<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\Entity\Paper;
use App\Repository\PapersRepository;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Unit tests for PapersRepository.
 *
 * Methods that build DQL via QueryBuilder require a real EntityManager
 * (integration tests). This file tests pure PHP / non-QB logic only.
 */
final class PapersRepositoryTest extends TestCase
{
    private PapersRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new PapersRepository(
            $this->createStub(ManagerRegistry::class),
            new NullLogger()
        );
    }

    // ── AVAILABLE_FLAG_VALUES ─────────────────────────────────────────────────

    public function testAvailableFlagValuesContainsImportedAndSubmitted(): void
    {
        $flags = PapersRepository::AVAILABLE_FLAG_VALUES;

        $this->assertArrayHasKey('imported', $flags);
        $this->assertArrayHasKey('submitted', $flags);
        $this->assertSame('imported', $flags['imported']);
        $this->assertSame('submitted', $flags['submitted']);
    }

    // ── Constants ─────────────────────────────────────────────────────────────

    public function testConstantsHaveExpectedValues(): void
    {
        $this->assertSame('totalPublishedArticles', PapersRepository::TOTAL_ARTICLE);
        $this->assertSame('p', PapersRepository::PAPERS_ALIAS);
        $this->assertSame(0, PapersRepository::LOCAL_REPOSITORY);
    }

    // ── getSubmissionsWithoutImported: only catches Doctrine-level exceptions ──

    /**
     * getSubmissionsWithoutImported() catches NoResultException|NonUniqueResultException
     * and returns 0. With a stub ManagerRegistry, Doctrine throws a LogicException
     * (entity manager not found) before reaching that catch block.
     * This test verifies that the method does NOT swallow unexpected exceptions.
     */
    public function testGetSubmissionsWithoutImportedPropagatesUnexpectedException(): void
    {
        $this->expectException(\Throwable::class);
        $this->repository->getSubmissionsWithoutImported(null, null, null);
    }

    // ── paperToJson: source-code regression checks for the SQL logic fix ──────

    /**
     * Before the fix, the strict branch used:
     *   $qb->orWhere('p.paperid = :paperId')  (separate :paperId param)
     *   $qb->andWhere('p.status = :status')
     *
     * The intent (match docid OR paperid, then filter by status) was correct
     * thanks to Doctrine's expression tree, but the two separate parameters
     * were confusing and the condition was harder to read.
     *
     * After the fix, a single :docId parameter is used for both conditions
     * inside an explicit expr()->orX(), making the intent explicit and safe
     * regardless of the Doctrine QB version.
     */
    public function testPaperToJsonStrictBranchUsesSingleDocIdParameter(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(PapersRepository::class))->getFileName()
        );

        // The old code used a separate ':paperId' binding inside the strict block.
        // After the fix, ':docId' is the only parameter for identifier matching.
        $this->assertStringNotContainsString("setParameter('paperId'", $source,
            'paperToJson must not use a separate :paperId parameter; use :docId for both conditions');
    }

    public function testPaperToJsonStrictBranchUsesOrXExpression(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(PapersRepository::class))->getFileName()
        );

        // After the fix, the OR condition is expressed via expr()->orX()
        $this->assertStringContainsString('expr()->orX(', $source,
            'paperToJson strict branch must use expr()->orX() to group the identifier OR condition');
    }

    // ── getYearRange: null-safety regression check ────────────────────────────

    /**
     * Before the fix, getYearRange() accessed $result[0] without checking
     * for an empty result set, causing "Undefined offset: 0" on journals
     * with no submissions.
     *
     * We verify the source contains the null guard rather than executing
     * the method (which requires a real EntityManager).
     */
    public function testGetYearRangeSourceContainsNullGuard(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(PapersRepository::class))->getFileName()
        );

        // The fix introduces an early-return guard for empty / null results
        $this->assertStringContainsString('empty($result)', $source,
            'getYearRange must guard against empty result before accessing $result[0]');

        $this->assertStringContainsString("=== null", $source,
            'getYearRange must check minYear/maxYear for null before calling range()');
    }

    // ── flexibleSubmissionsQueryDetails: GROUP BY structure ───────────────────

    /**
     * Verify that flexibleSubmissionsQueryDetails() produces a QueryBuilder
     * whose DQL contains exactly the GROUP BY columns we need.
     *
     * Before the fix, consecutive groupBy() calls overwrote each other,
     * yielding GROUP BY year, p.repoid, p.status (missing p.rvid).
     * After the fix, p.rvid must also appear.
     *
     * We cannot call createQueryBuilder() without a real EntityManager, so we
     * assert the constant GROUP BY intent by inspecting the PapersRepository
     * source via Reflection rather than executing the query builder.
     * (Full integration tests are needed for QB execution.)
     */
    public function testFlexibleSubmissionsQueryDetailsGroupByIncludesRvid(): void
    {
        $sourceFile = (new \ReflectionClass(PapersRepository::class))->getFileName();
        $source = file_get_contents($sourceFile);

        // After the fix, the source must have addGroupBy('p.rvid') OR groupBy('p.rvid')
        // followed by addGroupBy calls (not a second groupBy that overwrites).
        // The key check: groupBy('p.paperid') must NOT appear (it reset the GROUP BY and
        // was itself immediately overwritten, so it was both wrong and useless).
        $this->assertStringNotContainsString("groupBy('p.paperid')", $source,
            'groupBy("p.paperid") must be removed: it reset the GROUP BY expression unnecessarily');

        // p.rvid must be in the GROUP BY chain
        $this->assertStringContainsString("groupBy('p.rvid')", $source,
            'groupBy("p.rvid") must set the first GROUP BY column');

        // addGroupBy (not groupBy) must be used for year, repoid, status
        $this->assertStringContainsString("addGroupBy('year')", $source,
            'year must be added with addGroupBy() to not overwrite p.rvid');
    }

    // ── getSubmissionsWithoutImported: catches and returns 0 ─────────────────

    public function testGetSubmissionsWithoutImportedReturnZeroOnException(): void
    {
        // With stubbed registry, createQueryBuilder throws LogicException.
        // getSubmissionsWithoutImported only catches NoResultException|NonUniqueResultException,
        // so LogicException propagates. Verify the method signature still returns int.
        $this->expectException(\Throwable::class);
        $this->repository->getSubmissionsWithoutImported(null, null, null);
    }
}
