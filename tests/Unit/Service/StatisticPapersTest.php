<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use ApiPlatform\Validator\Exception\ValidationException;
use App\Entity\Paper;
use App\Entity\PaperConflicts;
use App\Entity\Review;
use App\Entity\ReviewSetting;
use App\Entity\User;
use App\Repository\PapersRepository;
use App\Service\StatisticPapers;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

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
        $this->expectException(ValidationException::class);

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

    /**
     * The paper lists ("withPapers") are reserved for the administrators and the editorial
     * secretaries of the journal; the platform administrators get no conflict filtering.
     *
     * @param list<string> $roles granted roles (with the hierarchy)
     * @param list<int>    $userConflicts PAPERIDs for which the user declared a "yes" conflict
     */
    #[DataProvider('conflictedPapersProvider')]
    public function testConflictedPapers(?int $userJournal, array $roles, ?string $coiEnabled, array $userConflicts, bool $accessible): void
    {
        $journal = $this->createStub(Review::class);
        $journal->method('getRvid')->willReturn(42);
        $journal->method('getSetting')->willReturnCallback(static fn(string $setting): ?string => match ($setting) {
            ReviewSetting::DISPLAY_STATISTICS => '1',
            'isCoiEnabled' => $coiEnabled,
            default => null,
        });

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($userJournal === null ? null : new User()->setUid(7)->setCurrentJournalID($userJournal));
        $security->method('isGranted')->willReturnCallback(static fn(string $role): bool => in_array($role, $roles, true));

        // the conflicts of user 7, if any
        $conflictsRepository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $conflicts = array_map(static fn(int $paperId): PaperConflicts => new PaperConflicts()->setPaperId($paperId), $userConflicts);
        $conflictsRepository->method('findBy')->with(['by' => 7, 'answer' => PaperConflicts::AVAILABLE_ANSWER['yes']])->willReturn($conflicts);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturnMap([[PaperConflicts::class, $conflictsRepository]]);

        $service = new StatisticPapers($entityManager, $security);

        if (!$accessible) {
            $this->expectException(AccessDeniedException::class);
        }

        $result = $service->getConflictedPapers($journal);
        $this->addToAssertionCount(1);

        if ($accessible) {
            // without COI, or for the platform administrator, no paper is hidden
            $this->assertSame([], array_values($result));
        }
    }

    public static function conflictedPapersProvider(): iterable
    {
        $admin = ['ROLE_ADMINISTRATOR', 'ROLE_SECRETARY'];
        $secretary = ['ROLE_SECRETARY'];
        $epiAdmin = ['ROLE_EPIADMIN', 'ROLE_SECRETARY'];
        $plainMember = ['ROLE_MEMBER'];

        yield 'not connected' => [null, [], null, [], false];
        yield 'member of the journal' => [42, $plainMember, null, [], false];
        yield 'administrator of another journal' => [43, $admin, null, [], false];
        yield 'secretary of another journal' => [43, $secretary, null, [], false];
        yield 'administrator of the journal, COI disabled' => [42, $admin, '0', [3], true];
        yield 'secretary of the journal, COI disabled' => [42, $secretary, '0', [3], true];
        yield 'administrator of the journal, COI enabled, no conflict' => [42, $admin, '1', [], true];
        yield 'secretary of the journal, COI enabled, no conflict' => [42, $secretary, '1', [], true];
        // platform administrator: exempt from the conflicts, whatever the journal
        yield 'platform administrator of another journal' => [43, $epiAdmin, '1', [3], true];
    }

    /**
     * @param list<string> $roles granted roles (with the hierarchy)
     */
    #[DataProvider('dashboardVisibilityProvider')]
    public function testDashboardVisibility(?string $visibility, ?int $userJournal, array $roles, bool $visible): void
    {
        $journal = $this->createStub(Review::class);
        $journal->method('getRvid')->willReturn(42);
        $journal->method('getSetting')->willReturnMap([[ReviewSetting::DISPLAY_STATISTICS, $visibility]]);

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($userJournal === null ? null : new User()->setUid(7)->setCurrentJournalID($userJournal));
        $security->method('isGranted')->willReturnCallback(static fn(string $role): bool => in_array($role, $roles, true));

        $service = new StatisticPapers($this->createStub(EntityManagerInterface::class), $security);

        if (!$visible) {
            $this->expectException(AccessDeniedException::class);
        }

        $service->denyAccessUnlessDashboardIsVisible($journal);
        $this->addToAssertionCount(1);
    }

    public static function dashboardVisibilityProvider(): iterable
    {
        $secretary = ['ROLE_SECRETARY'];
        $epiAdmin = ['ROLE_EPIADMIN', 'ROLE_SECRETARY'];

        yield 'no setting: hidden' => [null, null, [], false];
        yield 'hidden: anonymous' => ['0', null, [], false];
        yield 'hidden: secretary of the journal' => ['0', 42, $secretary, false];
        yield 'hidden: platform administrator' => ['0', 42, $epiAdmin, false];
        yield 'public: anonymous' => ['1', null, [], true];
        yield 'reserved: anonymous' => ['2', null, [], false];
        yield 'reserved: member of the journal' => ['2', 42, ['ROLE_MEMBER'], false];
        yield 'reserved: secretary of the journal' => ['2', 42, $secretary, true];
        yield 'reserved: secretary of another journal' => ['2', 43, $secretary, false];
        yield 'reserved: platform administrator' => ['2', 43, $epiAdmin, true];
        yield 'empty: hidden' => ['', 42, $epiAdmin, false];
        yield 'other value: public, as in Episciences' => ['3', null, [], true];
    }
}
