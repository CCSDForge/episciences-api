<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\Pagination;
use App\Doctrine\UnpaginatedCollectionLimitExtension;
use App\Entity\Paper;
use App\Entity\ReviewerReport;
use App\Entity\UserInvitation;
use App\Repository\PapersRepository;
use App\Repository\ReviewerReportRepository;
use App\Repository\UserInvitationRepository;
use App\Resource\Statistic;
use App\Service\Stats;
use App\State\StatisticStateProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * The evaluation statistics (3 indicators) requested without pagination are flagged
 * when capped, like the other collections (see TruncatedCollectionSubscriber).
 */
class StatisticStateProviderTest extends TestCase
{
    private const string EVALUATION_OPERATION = '_api_/statistics/evaluation_get_collection';

    private Request $request;

    protected function setUp(): void
    {
        $this->request = new Request();
    }

    public function testUnpaginatedIndicatorsAboveTheMaximumAreFlagged(): void
    {
        $this->provideEvaluation(pagination: 'false', maxItemsPerPage: 2);

        $this->assertSame(2, $this->request->attributes->get(UnpaginatedCollectionLimitExtension::TRUNCATED_ATTRIBUTE));
    }

    public function testCompleteUnpaginatedIndicatorsAreNotFlagged(): void
    {
        $this->provideEvaluation(pagination: 'false', maxItemsPerPage: 30);

        $this->assertFalse($this->request->attributes->has(UnpaginatedCollectionLimitExtension::TRUNCATED_ATTRIBUTE));
    }

    public function testPaginatedIndicatorsAreNotFlagged(): void
    {
        $this->provideEvaluation(pagination: 'true', maxItemsPerPage: 2);

        $this->assertFalse($this->request->attributes->has(UnpaginatedCollectionLimitExtension::TRUNCATED_ATTRIBUTE));
    }

    private function provideEvaluation(string $pagination, int $maxItemsPerPage): void
    {
        $papersRepository = $this->createStub(PapersRepository::class);
        $papersRepository->method('getYearRange')->willReturn([]);

        $reviewerReportRepository = $this->createStub(ReviewerReportRepository::class);
        $reviewerReportRepository->method('getReceivedReports')->willReturn($this->createQueryBuilder());

        $userInvitationRepository = $this->createStub(UserInvitationRepository::class);
        $userInvitationRepository->method('getReviewsRequested')->willReturn($this->createQueryBuilder());

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturnMap([
            [Paper::class, $papersRepository],
            [ReviewerReport::class, $reviewerReportRepository],
            [UserInvitation::class, $userInvitationRepository],
        ]);

        $provider = new StatisticStateProvider(
            $entityManager,
            $this->createStub(LoggerInterface::class),
            new Pagination(['client_enabled' => true]),
            $this->createStub(Stats::class)
        );

        $operation = new GetCollection(class: Statistic::class, name: self::EVALUATION_OPERATION, paginationMaximumItemsPerPage: $maxItemsPerPage);

        $provider->provide($operation, [], ['operation' => $operation, 'filters' => ['pagination' => $pagination], 'request' => $this->request]);
    }

    private function createQueryBuilder(): QueryBuilder
    {
        $query = $this->createStub(Query::class);
        $query->method('getResult')->willReturn([]);
        $query->method('getArrayResult')->willReturn([]);

        $queryBuilder = $this->createStub(QueryBuilder::class);
        $queryBuilder->method('getQuery')->willReturn($query);

        return $queryBuilder;
    }
}
