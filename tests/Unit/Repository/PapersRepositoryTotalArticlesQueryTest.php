<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\Entity\Section;
use App\Entity\Volume;
use App\Repository\PapersRepository;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Number of published articles of the volumes / sections (hydra:totalPublishedArticles).
 * Only the generated SQL is checked: the connection is never opened.
 */
class PapersRepositoryTotalArticlesQueryTest extends TestCase
{
    private PapersRepository $repository;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 3) . '/src/Entity'], true);
        $config->enableNativeLazyObjects(true);
        $connection = DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => '8.0'], $config);

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn(new EntityManager($connection, $config));

        $this->repository = new PapersRepository($registry, new NullLogger());
    }

    /**
     * Regression: with the volume identifiers of a journal, the papers only published in
     * secondary volumes (no primary volume) were not counted.
     */
    public function testVolumeIdentifiersIncludeTheSecondaryVolumes(): void
    {
        $sql = $this->repository->getTotalArticlesBySectionOrVolumeQuery(Volume::class, 16, [10, 11], 3)->getQuery()->getSQL();

        $this->assertMatchesRegularExpression('/LEFT JOIN VOLUME_PAPER \w+ ON \(\w+\.DOCID = \w+\.DOCID\)/', $sql);
        $this->assertMatchesRegularExpression('/\w+\.VID IN \(\?\) OR \w+\.VID IN \(\?\)/', $sql);
        $this->assertStringContainsString('COUNT(DISTINCT', $sql);
    }

    /**
     * Regression: without a journal, the single total was grouped by journal and volume,
     * so that getSingleScalarResult() failed and hydra:totalPublishedArticles was null.
     */
    public function testIdentifiersWithoutJournalGiveASingleTotal(): void
    {
        foreach ([Volume::class, Section::class] as $resourceClass) {
            $sql = $this->repository->getTotalArticlesBySectionOrVolumeQuery($resourceClass, 16, [10, 11])->getQuery()->getSQL();

            $this->assertStringNotContainsString('GROUP BY', $sql, $resourceClass);
        }
    }

    public function testWithoutIdentifierNorJournalTheTotalsAreGrouped(): void
    {
        $sql = $this->repository->getTotalArticlesBySectionOrVolumeQuery(Volume::class)->getQuery()->getSQL();

        $this->assertMatchesRegularExpression('/GROUP BY \w+\.RVID, \w+\.VID/', $sql);
    }

    public function testSectionIdentifiersOnlyUseThePaperSection(): void
    {
        $sql = $this->repository->getTotalArticlesBySectionOrVolumeQuery(Section::class, 16, [5], 3)->getQuery()->getSQL();

        $this->assertStringNotContainsString('VOLUME_PAPER', $sql);
        $this->assertMatchesRegularExpression("/\w+\.SID = 5$/", $sql);
    }
}
