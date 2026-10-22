<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\Repository\PapersRepository;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\Persistence\ManagerRegistry;
use DoctrineExtensions\Query\Mysql\Year;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Submissions, imported and submitted papers of GET /api/statistics/ in a single query.
 * Only the generated SQL is checked: the connection is never opened.
 */
class PapersRepositorySubmissionsByFlagQueryTest extends TestCase
{
    private PapersRepository $repository;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 3) . '/src/Entity'], true);
        $config->enableNativeLazyObjects(true);
        $config->addCustomStringFunction('YEAR', Year::class);
        $connection = DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => '8.0'], $config);

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn(new EntityManager($connection, $config));

        $this->repository = new PapersRepository($registry, new NullLogger());
    }

    public function testWithoutStatusNorFlagTheTotalCountsEverySubmission(): void
    {
        $sql = $this->sql([]);

        $this->assertMatchesRegularExpression('/^SELECT COUNT\(DISTINCT \w+\.PAPERID\) AS \w+, COUNT\(DISTINCT CASE WHEN \w+\.FLAG = \? THEN \w+\.PAPERID ELSE NULL END\) AS \w+, COUNT\(DISTINCT CASE WHEN \w+\.FLAG = \? THEN \w+\.PAPERID ELSE NULL END\) AS \w+ FROM PAPERS/', $sql);
        $this->assertStringContainsString('STATUS <> ?', $sql);
    }

    /**
     * The journal, year and date filters apply to the three numbers: they are in the WHERE clause.
     */
    public function testCommonFiltersRestrictTheThreeNumbers(): void
    {
        $sql = $this->sql(['rvid' => 3, 'year' => [2024], 'startAfterDate' => '2022-01-01']);

        $where = substr($sql, (int)strpos($sql, ' WHERE '));
        $this->assertStringContainsString('RVID = ?', $where);
        $this->assertMatchesRegularExpression("/YEAR\(\w+\.SUBMISSION_DATE\) = '?2024'?/", $where);
        $this->assertMatchesRegularExpression('/SUBMISSION_DATE >= \?/', $where);
    }

    /**
     * Same results as the former queries: the status filter applies to the total and the imported papers,
     * the flag filter to the total only, and neither to the submitted papers.
     */
    public function testStatusAndFlagFiltersOnlyApplyToTheirNumbers(): void
    {
        $qb = $this->repository->countSubmissionsByFlagQuery(['status' => [16], 'flag' => 'imported']);
        $sql = $qb->getQuery()->getSQL();

        $this->assertMatchesRegularExpression('/^SELECT COUNT\(DISTINCT CASE WHEN \w+\.STATUS IN \(\?\) AND \w+\.FLAG = \? THEN/', $sql);
        $this->assertMatchesRegularExpression('/COUNT\(DISTINCT CASE WHEN \w+\.FLAG = \? AND \w+\.STATUS IN \(\?\) THEN/', $sql);
        $this->assertMatchesRegularExpression('/COUNT\(DISTINCT CASE WHEN \w+\.FLAG = \? THEN \w+\.PAPERID ELSE NULL END\) AS \w+ FROM/', $sql);
        $this->assertStringNotContainsString('STATUS IN', substr($sql, (int)strpos($sql, ' WHERE ')));

        $this->assertSame('imported', $qb->getParameter('importedFlag')?->getValue());
        $this->assertSame('submitted', $qb->getParameter('submittedFlag')?->getValue());
        $this->assertSame('imported', $qb->getParameter('flagFilter')?->getValue());
        $this->assertSame([16], $qb->getParameter('statusFilter')?->getValue());
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function sql(array $filters): string
    {
        return $this->repository->countSubmissionsByFlagQuery($filters)->getQuery()->getSQL();
    }
}
