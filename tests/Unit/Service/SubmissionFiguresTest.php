<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Paper;
use App\Service\SubmissionFigures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The figures of the statistics counted by submission: one category by submission, from its last decision.
 */
class SubmissionFiguresTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function submission(int $docId, string $submitted, int $status = 0, ?string $accepted = null, ?string $refused = null, string $flag = 'submitted', int $rvId = 8, ?int $repoId = 1): array
    {
        $submission = [
            'rvid' => $rvId,
            'paperid' => $docId,
            'docid' => $docId,
            'status' => $status,
            'flag' => $flag,
            'repoid' => $repoId,
            'firstSubmissionDate' => $submitted,
            'acceptanceDate' => $accepted,
            'refusalDate' => $refused,
        ];
        $submission['category'] = SubmissionFigures::category($submission);

        return $submission;
    }

    #[DataProvider('categoryProvider')]
    public function testCategory(array $submission, string $expected): void
    {
        $this->assertSame($expected, $submission['category']);
    }

    public static function categoryProvider(): iterable
    {
        yield 'no decision' => [self::submission(1, '2025-01-01'), SubmissionFigures::OTHER];
        yield 'accepted' => [self::submission(1, '2025-01-01', Paper::STATUS_STRICTLY_ACCEPTED, '2025-03-01'), SubmissionFigures::ACCEPTED];
        yield 'temporary version accepted, then refused: refused only' => [self::submission(1, '2025-01-01', Paper::STATUS_REFUSED, '2025-03-01', '2025-06-01'), SubmissionFigures::REFUSED];
        yield 'refused, then accepted' => [self::submission(1, '2025-01-01', Paper::STATUS_CE_READY_TO_PUBLISH, '2025-06-01', '2025-03-01'), SubmissionFigures::ACCEPTED];
        yield 'published, a refusal logged later on an obsolete version: accepted' => [self::submission(1, '2018-01-01', Paper::STATUS_PUBLISHED, '2018-01-06', '2021-02-12'), SubmissionFigures::ACCEPTED];
        yield 'published without acceptance in the history (old data)' => [self::submission(1, '2014-01-01', Paper::STATUS_PUBLISHED), SubmissionFigures::ACCEPTED];
        yield 'refused without refusal in the history' => [self::submission(1, '2014-01-01', Paper::STATUS_REFUSED), SubmissionFigures::REFUSED];
        yield 'abandoned after an acceptance: still accepted' => [self::submission(1, '2025-01-01', Paper::STATUS_ABANDONED, '2025-03-01'), SubmissionFigures::ACCEPTED];
        yield 'imported (all its versions)' => [self::submission(1, '2025-01-01', Paper::STATUS_PUBLISHED, '2025-03-01', flag: 'imported'), SubmissionFigures::IMPORTED];
    }

    /**
     * Without year, the categories add up to the submissions; by year, accepted and refused are counted in the year of
     * the decision, the others in the year of submission.
     */
    public function testCategoriesAndYears(): void
    {
        $submissions = [
            self::submission(1, '2024-05-01 10:00:00', Paper::STATUS_PUBLISHED, '2025-02-01 10:00:00'),
            self::submission(2, '2025-01-01 10:00:00', Paper::STATUS_STRICTLY_ACCEPTED, '2025-04-01 10:00:00'),
            self::submission(3, '2025-02-01 10:00:00', Paper::STATUS_REFUSED, null, '2026-01-10 10:00:00'),
            self::submission(4, '2025-03-01 10:00:00'),
            self::submission(5, '2025-04-01 10:00:00', Paper::STATUS_PUBLISHED, flag: 'imported'),
        ];

        $counts = array_map(static fn(string $category): int => count(SubmissionFigures::inCategory($submissions, $category)), [
            SubmissionFigures::IMPORTED, SubmissionFigures::ACCEPTED, SubmissionFigures::REFUSED, SubmissionFigures::OTHER,
        ]);
        $this->assertSame([1, 2, 1, 1], $counts);
        $this->assertSame(count($submissions), array_sum($counts));

        $this->assertCount(4, SubmissionFigures::submittedIn($submissions, [2025]));
        $this->assertSame([1, 2], array_column(SubmissionFigures::inCategory($submissions, SubmissionFigures::ACCEPTED, ['2025']), 'docid'));
        $this->assertSame([], SubmissionFigures::inCategory($submissions, SubmissionFigures::REFUSED, [2025]));
        $this->assertSame([3], array_column(SubmissionFigures::inCategory($submissions, SubmissionFigures::REFUSED, [2026]), 'docid'));
        $this->assertSame([4], array_column(SubmissionFigures::inCategory($submissions, SubmissionFigures::OTHER, [2025]), 'docid'));
        $this->assertSame([2], array_column(SubmissionFigures::acceptedNotYetPublished($submissions), 'docid'));

        $this->assertSame([2024 => 1, 2025 => 4], SubmissionFigures::countByYear($submissions));
        $this->assertSame([2025 => 2], SubmissionFigures::countByYear($submissions, SubmissionFigures::ACCEPTED));
        $this->assertSame([2026 => 1], SubmissionFigures::countByYear($submissions, SubmissionFigures::REFUSED));
        $this->assertSame([2024, 2025], SubmissionFigures::years($submissions));
    }

    public function testFilterOnTheCurrentStatusAndTheFlag(): void
    {
        $submissions = [
            self::submission(1, '2025-01-01', Paper::STATUS_REFUSED, null, '2025-02-01'),
            self::submission(2, '2025-01-01', Paper::STATUS_PUBLISHED, '2025-02-01'),
            self::submission(3, '2025-01-01', Paper::STATUS_PUBLISHED, flag: 'imported'),
        ];

        $this->assertCount(3, SubmissionFigures::filter($submissions));
        $this->assertSame([2, 3], array_column(SubmissionFigures::filter($submissions, [Paper::STATUS_PUBLISHED]), 'docid'));
        $this->assertSame([3], array_column(SubmissionFigures::filter($submissions, ['16'], 'imported'), 'docid'));
        $this->assertSame([1, 2], array_column(SubmissionFigures::filter($submissions, [], 'submitted'), 'docid'));
    }

    public function testCountByJournalAndYearsBeforeTheReferenceYearAreIgnored(): void
    {
        $submissions = [
            self::submission(1, '2010-01-01', rvId: 3),
            self::submission(2, '2025-01-01', rvId: 3),
            self::submission(3, '2025-01-01', rvId: 8),
        ];

        $this->assertSame([3 => 2, 8 => 1], SubmissionFigures::countByJournal($submissions));
        $this->assertSame([2025], SubmissionFigures::years($submissions));
    }

    public function testPapersAreSortedByDocId(): void
    {
        $papers = SubmissionFigures::papers([self::submission(9, '2025-01-01'), self::submission(4, '2025-01-01')]);

        $this->assertSame([['paperid' => 4, 'docid' => 4], ['paperid' => 9, 'docid' => 9]], $papers);
    }
}
