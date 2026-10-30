<?php

namespace App\Tests\Unit\DataProvider;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Validator\Exception\ValidationException;
use App\AppConstants;
use App\DataProvider\AbstractDataProvider;
use App\Entity\Review;
use App\Entity\ReviewSetting;
use App\Exception\ResourceNotFoundException;
use App\Resource\DashboardOutput;
use App\Resource\SubmissionOutput;
use App\Resource\UsersStatsOutput;
use App\Resource\SubmissionAcceptanceDelayOutput;
use App\Resource\SubmissionPublicationDelayOutput;
use App\Entity\User;
use App\Repository\PapersRepository;
use App\Service\StatisticPapers;
use App\Service\Stats;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class TestableDataProvider extends AbstractDataProvider
{
    public function supports(?Operation $operation = null): bool
    {
        return true;
    }

    public function exposeGetCollection(Operation $operation, array $context = []): mixed
    {
        return $this->getCollection($operation, $context);
    }
}

class AbstractDataProviderTest extends TestCase
{
    private TestableDataProvider $dataProvider;
    private MockObject $entityManager;
    private MockObject $statsService;
    private MockObject $security;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->statsService = $this->createMock(Stats::class);
        $this->security = $this->createMock(Security::class);
        $this->dataProvider = new TestableDataProvider($this->entityManager, $this->statsService, new StatisticPapers($this->entityManager, $this->security));
    }

    /**
     * withPapers: the papers of each figure (DOCID of the latest version) are added to the value of the dashboard.
     */
    public function testDashboardWithPapersAddsThePapersOfEachFigure(): void
    {
        $journal = $this->createStub(Review::class);
        $journal->method('getRvid')->willReturn(42);
        $journal->method('getSetting')->willReturnMap([[ReviewSetting::DISPLAY_STATISTICS, ReviewSetting::DISPLAY_STATISTICS_PUBLIC]]);
        $this->statsService->method('getJournal')->willReturn($journal);
        $this->statsService->method('getDashboard')->willReturn(new DashboardOutput()->setValue(['nbSubmissions' => 3]));
        $this->statsService->method('getDashboardPaperIds')->willReturn(['nbSubmissions' => [1, 2, 3], 'nbOtherStatus' => [3]]);

        $papersRepository = $this->createStub(PapersRepository::class);
        $papersRepository->method('getLatestDocIds')->willReturnCallback(static fn(array $paperIds): array => array_combine($paperIds, array_map(static fn(int $id): int => 100 + $id, $paperIds)));
        $this->entityManager->method('getRepository')->willReturn($papersRepository);

        $this->security->method('isGranted')->willReturnCallback(static fn(string $role): bool => $role === 'ROLE_EPIADMIN');

        $operation = new Get(name: AppConstants::APP_CONST['custom_operations']['items']['review'][0]);
        $result = $this->dataProvider->exposeGetCollection($operation, ['uri_variables' => ['code' => 'myjournal'], 'filters' => ['withPapers' => 'true']]);

        $this->assertSame([
            'nbSubmissions' => 3,
            'papers' => ['nbSubmissions' => [101, 102, 103], 'nbOtherStatus' => [103]],
            'hiddenPapers' => ['nbSubmissions' => 0, 'nbOtherStatus' => 0],
        ], $result->getValue());
    }

    /**
     * Checked before any computation: the dashboard is not computed for a user who cannot get the papers.
     */
    public function testDashboardWithPapersIsDeniedToAnAdministratorOfAnotherJournal(): void
    {
        $journal = $this->createStub(Review::class);
        $journal->method('getRvid')->willReturn(42);
        $journal->method('getSetting')->willReturnMap([[ReviewSetting::DISPLAY_STATISTICS, ReviewSetting::DISPLAY_STATISTICS_PUBLIC]]);
        $this->statsService->method('getJournal')->willReturn($journal);
        $this->statsService->expects($this->never())->method('getDashboard');

        $this->security->method('getUser')->willReturn(new User()->setUid(7)->setCurrentJournalID(43));
        // as in the role hierarchy, ROLE_ADMINISTRATOR covers ROLE_SECRETARY, the role
        // actually checked by StatisticPapers::getConflictedPapers
        $this->security->method('isGranted')->willReturnCallback(static fn(string $role): bool => in_array($role, ['ROLE_ADMINISTRATOR', 'ROLE_SECRETARY'], true));

        $this->expectException(AccessDeniedException::class);

        $operation = new Get(name: AppConstants::APP_CONST['custom_operations']['items']['review'][0]);
        $this->dataProvider->exposeGetCollection($operation, ['uri_variables' => ['code' => 'myjournal'], 'filters' => ['withPapers' => 'true']]);
    }

    public function testWithPapersIsOnlyAvailableOnTheDashboard(): void
    {
        $journal = $this->createStub(Review::class);
        $journal->method('getRvid')->willReturn(42);
        $this->statsService->method('getJournal')->willReturn($journal);

        $this->expectException(ValidationException::class);

        $operation = new Get(name: AppConstants::APP_CONST['custom_operations']['items']['review'][1]);
        $this->dataProvider->exposeGetCollection($operation, ['uri_variables' => ['code' => 'myjournal'], 'filters' => ['withPapers' => 'true']]);
    }

    public function testThrowsResourceNotFoundExceptionWhenJournalNotFound(): void
    {
        $this->statsService->method('getJournal')->willReturn(null);

        $operation = new Get();
        $context = [
            'uri_variables' => ['code' => 'unknown']
        ];

        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessage('Oops! not found Journal unknown');

        $this->dataProvider->exposeGetCollection($operation, $context);
    }

    public function testDashboardItemCallsGetDashboard(): void
    {
        $journal = $this->createMock(Review::class);
        $journal->method('getRvid')->willReturn(42);
        $journal->method('getSetting')->willReturnMap([[ReviewSetting::DISPLAY_STATISTICS, ReviewSetting::DISPLAY_STATISTICS_PUBLIC]]);

        $this->statsService->method('getJournal')->willReturn($journal);

        $dashboardOutput = new DashboardOutput();
        $this->statsService->expects($this->once())
            ->method('getDashboard')
            ->willReturn($dashboardOutput);

        $operation = new Get(name: AppConstants::APP_CONST['custom_operations']['items']['review'][0]);
        
        $context = ['uri_variables' => ['code' => 'myjournal'], 'filters' => []];

        $result = $this->dataProvider->exposeGetCollection($operation, $context);

        $this->assertSame($dashboardOutput, $result);
    }

    /**
     * The setting "displayStatistics" of the journal: the dashboard is not computed when it is hidden.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('hiddenDashboardProvider')]
    public function testHiddenDashboardIsDenied(?string $visibility): void
    {
        $journal = $this->createStub(Review::class);
        $journal->method('getRvid')->willReturn(42);
        $journal->method('getSetting')->willReturnMap([[ReviewSetting::DISPLAY_STATISTICS, $visibility]]);
        $this->statsService->method('getJournal')->willReturn($journal);
        $this->statsService->expects($this->never())->method('getDashboard');

        $this->expectException(AccessDeniedException::class);

        $operation = new Get(name: AppConstants::APP_CONST['custom_operations']['items']['review'][0]);
        $this->dataProvider->exposeGetCollection($operation, ['uri_variables' => ['code' => 'myjournal'], 'filters' => []]);
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function hiddenDashboardProvider(): iterable
    {
        yield 'no setting (hidden by default)' => [null];
        yield 'hidden' => ['0'];
        yield 'reserved, anonymous user' => [ReviewSetting::DISPLAY_STATISTICS_SECRETARIES];
    }

    /**
     * The other statistics of the journal do not depend on the setting "displayStatistics".
     */
    public function testHiddenDashboardDoesNotApplyToTheOtherStatistics(): void
    {
        $journal = $this->createStub(Review::class);
        $journal->method('getRvid')->willReturn(42);
        $this->statsService->method('getJournal')->willReturn($journal);
        $this->statsService->expects($this->once())->method('getSubmissionsStat')->willReturn(new SubmissionOutput());

        $operation = new Get(name: AppConstants::APP_CONST['custom_operations']['items']['review'][1]);
        $this->dataProvider->exposeGetCollection($operation, ['uri_variables' => ['code' => 'myjournal'], 'filters' => []]);
    }

    public function testNbSubmissionsItemCallsGetSubmissionsStat(): void
    {
        $journal = $this->createMock(Review::class);
        $journal->method('getRvid')->willReturn(42);

        $this->statsService->method('getJournal')->willReturn($journal);

        $submissionOutput = new SubmissionOutput();
        $this->statsService->expects($this->once())
            ->method('getSubmissionsStat')
            ->willReturn($submissionOutput);

        $operation = new Get(name: AppConstants::APP_CONST['custom_operations']['items']['review'][1]);
        $context = ['uri_variables' => ['code' => 'myjournal'], 'filters' => []];

        $result = $this->dataProvider->exposeGetCollection($operation, $context);

        $this->assertSame($submissionOutput, $result);
    }

    public function testAddFiltersTransformsContextFiltersCorrectly(): void
    {
        $journal = $this->createMock(Review::class);
        $journal->method('getRvid')->willReturn(42);

        $this->statsService->method('getJournal')->willReturn($journal);

        // We use STATS_NB_SUBMISSIONS_ITEM because it enables additional filters
        $operation = new Get(name: AppConstants::STATS_NB_SUBMISSIONS_ITEM);
        $context = [
            'uri_variables' => ['code' => 'myjournal'],
            'filters' => [
                AppConstants::WITH_DETAILS => 'true',
                AppConstants::START_AFTER_DATE => '2023-01-01',
                AppConstants::YEAR_PARAM => '2022'
            ]
        ];

        $this->statsService->expects($this->once())
            ->method('getSubmissionsStat')
            ->with($this->callback(
                // Validates that addFilters mapped parameters correctly
                fn(array $filters): bool => isset($filters['is']['rvid']) && $filters['is']['rvid'] === '42'
                && isset($filters['is'][AppConstants::WITH_DETAILS]) && $filters['is'][AppConstants::WITH_DETAILS] === true
                && isset($filters['is'][AppConstants::START_AFTER_DATE]) && $filters['is'][AppConstants::START_AFTER_DATE] === '2023-01-01'
                && isset($filters['is']['submissionDate']) && $filters['is']['submissionDate'] === [2022]))
            ->willReturn(new SubmissionOutput());

        $this->dataProvider->exposeGetCollection($operation, $context);
    }

    /**
     * An invalid startAfterDate is rejected, as for /api/statistics/*: it must not reach the query.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidStartAfterDateProvider')]
    public function testInvalidStartAfterDateIsRejected(mixed $startAfterDate): void
    {
        $journal = $this->createMock(Review::class);
        $journal->method('getRvid')->willReturn(42);

        $this->statsService->method('getJournal')->willReturn($journal);
        $this->statsService->expects($this->never())->method('getSubmissionsStat');

        $operation = new Get(name: AppConstants::STATS_NB_SUBMISSIONS_ITEM);
        $context = [
            'uri_variables' => ['code' => 'myjournal'],
            'filters' => [AppConstants::START_AFTER_DATE => $startAfterDate],
        ];

        $this->expectException(ValidationException::class);

        $this->dataProvider->exposeGetCollection($operation, $context);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidStartAfterDateProvider(): iterable
    {
        yield 'not a date' => ['abc'];
        yield 'impossible date' => ['2023-02-30'];
        yield 'incomplete date' => ['2023-1-1'];
        yield 'date sent as array' => [['2023-01-01']];
    }

    /**
     * An invalid year is rejected, as for /api/statistics/*: it must not reach the query.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidYearProvider')]
    public function testInvalidYearIsRejected(mixed $year): void
    {
        $journal = $this->createMock(Review::class);
        $journal->method('getRvid')->willReturn(42);

        $this->statsService->method('getJournal')->willReturn($journal);
        $this->statsService->expects($this->never())->method('getSubmissionsStat');

        $operation = new Get(name: AppConstants::STATS_NB_SUBMISSIONS_ITEM);
        $context = [
            'uri_variables' => ['code' => 'myjournal'],
            'filters' => [AppConstants::YEAR_PARAM => $year],
        ];

        $this->expectException(ValidationException::class);

        $this->dataProvider->exposeGetCollection($operation, $context);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidYearProvider(): iterable
    {
        yield 'not a year' => ['abc'];
        yield 'incomplete year' => ['24'];
        yield 'one invalid year among several' => [['2023', 'abc']];
        yield 'nested array' => [[['2023']]];
    }

    /**
     * Several valid years are passed to the query as integers.
     */
    public function testValidYearsAreConvertedToIntegers(): void
    {
        $journal = $this->createMock(Review::class);
        $journal->method('getRvid')->willReturn(42);

        $this->statsService->method('getJournal')->willReturn($journal);

        $operation = new Get(name: AppConstants::STATS_NB_SUBMISSIONS_ITEM);
        $context = [
            'uri_variables' => ['code' => 'myjournal'],
            'filters' => [AppConstants::YEAR_PARAM => ['2023', '2024', '2023']],
        ];

        $this->statsService->expects($this->once())
            ->method('getSubmissionsStat')
            ->with($this->callback(fn(array $filters): bool => $filters['is']['submissionDate'] === [2023, 2024]))
            ->willReturn(new SubmissionOutput());

        $this->dataProvider->exposeGetCollection($operation, $context);
    }

    /**
     * An empty startAfterDate is ignored.
     */
    public function testEmptyStartAfterDateIsIgnored(): void
    {
        $journal = $this->createMock(Review::class);
        $journal->method('getRvid')->willReturn(42);

        $this->statsService->method('getJournal')->willReturn($journal);

        $operation = new Get(name: AppConstants::STATS_NB_SUBMISSIONS_ITEM);
        $context = [
            'uri_variables' => ['code' => 'myjournal'],
            'filters' => [AppConstants::START_AFTER_DATE => ''],
        ];

        $this->statsService->expects($this->once())
            ->method('getSubmissionsStat')
            ->with($this->callback(fn(array $filters): bool => !array_key_exists(AppConstants::START_AFTER_DATE, $filters['is'])))
            ->willReturn(new SubmissionOutput());

        $this->dataProvider->exposeGetCollection($operation, $context);
    }
}
