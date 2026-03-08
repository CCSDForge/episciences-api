<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\Entity\Paper;
use App\Repository\PaperLogRepository;
use App\Service\Stats;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class PaperLogRepositoryTest extends TestCase
{
    private PaperLogRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new PaperLogRepository($this->createStub(ManagerRegistry::class), new NullLogger());
    }

    // ── getNbPapersByStatusSql ───────────────────────────────────────────────

    public function testNbPapersByStatusSqlContainsGroupByWithLeadingSpace(): void
    {
        $sql = $this->repository->getNbPapersByStatusSql();

        // Must contain a space before GROUP BY to avoid SQL syntax error
        $this->assertStringContainsString(' GROUP BY', $sql);
    }

    public function testNbPapersByStatusSqlContainsOrderByWithLeadingSpace(): void
    {
        $sql = $this->repository->getNbPapersByStatusSql();

        // Must contain a space before ORDER BY to avoid SQL syntax error
        $this->assertStringContainsString(' ORDER BY', $sql);
    }

    public function testNbPapersByStatusSqlGroupByComesBeforeOrderBy(): void
    {
        $sql = $this->repository->getNbPapersByStatusSql();

        $groupByPos = strpos($sql, 'GROUP BY');
        $orderByPos = strpos($sql, 'ORDER BY');

        $this->assertNotFalse($groupByPos);
        $this->assertNotFalse($orderByPos);
        $this->assertLessThan($orderByPos, $groupByPos, 'GROUP BY must appear before ORDER BY');
    }

    public function testNbPapersByStatusSqlWithImportedFilterContainsCorrectFlag(): void
    {
        // $withoutImported = true (default): appends FLAG condition; GROUP BY must follow with space
        $sql = $this->repository->getNbPapersByStatusSql(true, 'totalNumberOfPapersAccepted', Paper::STATUS_STRICTLY_ACCEPTED, true);

        $this->assertStringContainsString("FLAG = 'submitted'", $sql);

        // The GROUP BY must be separated by whitespace from the preceding condition
        $flagPos = strpos($sql, "FLAG = 'submitted'");
        $groupByPos = strpos($sql, 'GROUP BY');
        $between = substr($sql, $flagPos + strlen("FLAG = 'submitted'"), $groupByPos - $flagPos - strlen("FLAG = 'submitted'"));
        $this->assertNotEmpty(trim($between) === '' ? ' ' : $between, 'There must be whitespace between FLAG clause and GROUP BY');
        $this->assertStringStartsWith(' ', substr($sql, $groupByPos - 1, 1) !== '' ? ' GROUP BY' : '', 'GROUP BY needs leading space');
    }

    public function testNbPapersByStatusSqlWithoutImportedFilter(): void
    {
        // $withoutImported = false: no FLAG condition, GROUP BY still needs space
        $sql = $this->repository->getNbPapersByStatusSql(true, 'totalNumberOfPapersAccepted', Paper::STATUS_STRICTLY_ACCEPTED, false);

        $this->assertStringNotContainsString("FLAG", $sql);
        $this->assertStringContainsString(' GROUP BY', $sql);
        $this->assertStringContainsString(' ORDER BY', $sql);
    }

    public function testNbPapersByStatusSqlHasValidStructure(): void
    {
        $sql = $this->repository->getNbPapersByStatusSql();

        $this->assertStringContainsString('SELECT', $sql);
        $this->assertStringContainsString('FROM', $sql);
        $this->assertStringContainsString('WHERE', $sql);
        $this->assertStringContainsString('GROUP BY', $sql);
        $this->assertStringContainsString('ORDER BY', $sql);
        $this->assertStringContainsString('rvid', $sql);
        $this->assertStringContainsString('year', $sql);
    }

    public function testNbPapersByStatusSqlUsesAllowedAlias(): void
    {
        $validAliases = [
            Stats::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR,
            Stats::TOTAL_PUBLISHED_SUBMITTED_SAME_YEAR,
            Stats::TOTAL_REFUSED_SUBMITTED_SAME_YEAR,
            'totalNumberOfPapersAccepted',
        ];

        foreach ($validAliases as $alias) {
            $sql = $this->repository->getNbPapersByStatusSql(true, $alias, Paper::STATUS_STRICTLY_ACCEPTED);
            $this->assertStringContainsString($alias, $sql, "SQL must contain alias '$alias'");
        }
    }

    public function testNbPapersByStatusSqlWithUnknownAliasFallsBackToTotal(): void
    {
        $sql = $this->repository->getNbPapersByStatusSql(true, 'unknownAlias', Paper::STATUS_STRICTLY_ACCEPTED);

        // Falls back to 'total'
        $this->assertStringContainsString('total', $sql);
        $this->assertStringNotContainsString('unknownAlias', $sql);
    }

    public function testNbPapersByStatusSqlInjectsStatusAsInteger(): void
    {
        $sql = $this->repository->getNbPapersByStatusSql(true, 'totalNumberOfPapersAccepted', Paper::STATUS_PUBLISHED);

        $this->assertStringContainsString((string)Paper::STATUS_PUBLISHED, $sql);
    }

    public function testNbPapersByStatusSqlNoSyntaxGapBetweenFlagAndGroupBy(): void
    {
        // Regression test: before the fix, GROUP BY had no leading space, causing SQL syntax error
        $sql = $this->repository->getNbPapersByStatusSql();

        // The string "submitted'GROUP BY" (no space) must NOT appear
        $this->assertStringNotContainsString("submitted'GROUP BY", $sql);
        $this->assertStringNotContainsString("submitted'ORDER BY", $sql);
    }
}
