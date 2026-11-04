<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Paper;
use App\Repository\PapersRepository;

/**
 * The figures of the statistics counted by submission (see PapersRepository::getSubmissions()).
 *
 * A submission is not a paper (PAPERID): a refused paper that the journal accepts to receive again keeps its row
 * (status "refused", identifier suffixed with "-REFUSED") and its new submission is a new row with the same PAPERID
 * (see Episciences_Submit in Episciences). Counting by PAPERID merged the two submissions, so that the statistics did
 * not give the number of the list of papers of the journal, and a paper refused, then submitted again and accepted,
 * was counted as accepted and as refused.
 *
 * Each submission falls into a single category, from its last decision, so that the figures add up:
 * submissions = imported + accepted + refused + other (without year; by year, see inCategory()).
 *
 * @phpstan-type Submission array{rvid: int, paperid: int, docid: int, status: int, flag: string, repoid: int|null, firstSubmissionDate: string, acceptanceDate: string|null, refusalDate: string|null, category: string}
 */
final class SubmissionFigures
{
    public const string IMPORTED = 'imported';
    public const string ACCEPTED = 'accepted';
    public const string REFUSED = 'refused';
    /** awaiting a decision */
    public const string OTHER = 'other';

    /**
     * The category of a submission:
     * - imported: all its versions are imported (some papers reviewed in the journal have an imported last version:
     *   they are submissions);
     * - accepted / refused: the current status first (published or refused), since a decision may have been logged
     *   later on an obsolete version; otherwise the last decision logged on one of its versions;
     * - other: no decision yet.
     *
     * @param array{status: int, flag: string, acceptanceDate: string|null, refusalDate: string|null} $submission
     */
    public static function category(array $submission): string
    {
        if ($submission['flag'] === PapersRepository::AVAILABLE_FLAG_VALUES['imported']) {
            return self::IMPORTED;
        }

        if ($submission['status'] === Paper::STATUS_PUBLISHED) {
            return self::ACCEPTED;
        }

        if ($submission['status'] === Paper::STATUS_REFUSED) {
            return self::REFUSED;
        }

        $acceptanceDate = $submission['acceptanceDate'];
        $refusalDate = $submission['refusalDate'];

        if ($refusalDate !== null && ($acceptanceDate === null || $refusalDate >= $acceptanceDate)) {
            return self::REFUSED;
        }

        return $acceptanceDate !== null ? self::ACCEPTED : self::OTHER;
    }

    /**
     * The submissions whose current status is one of $statuses (all if empty) and whose flag is $flag (all if null)
     *
     * @param list<Submission> $submissions
     * @param int[] $statuses
     * @return list<Submission>
     */
    public static function filter(array $submissions, array $statuses = [], ?string $flag = null): array
    {
        $statuses = array_map('intval', $statuses);

        return array_values(array_filter(
            $submissions,
            static fn(array $submission): bool => ($statuses === [] || in_array($submission['status'], $statuses, true))
                && ($flag === null || $flag === '' || $submission['flag'] === $flag)
        ));
    }

    /**
     * The submissions first submitted in one of $years (all if empty)
     *
     * @param list<Submission> $submissions
     * @param array<int|string> $years
     * @return list<Submission>
     */
    public static function submittedIn(array $submissions, array $years = []): array
    {
        return self::inYears($submissions, $years, self::submissionYear(...));
    }

    /**
     * The submissions of a category.
     *
     * With years, the accepted and refused submissions are those decided in one of these years (date of the last
     * acceptance or refusal), as before; the other ones (imported, awaiting a decision) are those first submitted in
     * one of these years. So, by year, accepted + refused + other is not the number of submissions of the year:
     * a submission of a previous year can be decided this year.
     *
     * @param list<Submission> $submissions
     * @param array<int|string> $years
     * @return list<Submission>
     */
    public static function inCategory(array $submissions, string $category, array $years = []): array
    {
        $inCategory = array_values(array_filter($submissions, static fn(array $submission): bool => $submission['category'] === $category));

        return self::inYears($inCategory, $years, self::yearOf($category));
    }

    /**
     * The accepted submissions not published yet (an abandoned paper is only paused: it is still counted),
     * by year of their acceptance
     *
     * @param list<Submission> $submissions
     * @param array<int|string> $years
     * @return list<Submission>
     */
    public static function acceptedNotYetPublished(array $submissions, array $years = []): array
    {
        return array_values(array_filter(
            self::inCategory($submissions, self::ACCEPTED, $years),
            static fn(array $submission): bool => $submission['status'] !== Paper::STATUS_PUBLISHED
        ));
    }

    /**
     * Number of submissions of a category by year (see inCategory() for the year of each category)
     *
     * @param list<Submission> $submissions
     * @return array<int, int> indexed by year
     */
    public static function countByYear(array $submissions, ?string $category = null): array
    {
        $yearOf = $category === null ? self::submissionYear(...) : self::yearOf($category);
        $counts = [];

        foreach ($submissions as $submission) {
            if ($category !== null && $submission['category'] !== $category) {
                continue;
            }

            $year = $yearOf($submission);

            if ($year !== null) {
                $counts[$year] = ($counts[$year] ?? 0) + 1;
            }
        }

        ksort($counts);

        return $counts;
    }

    /**
     * Number of submissions by journal
     *
     * @param list<Submission> $submissions
     * @return array<int, int> indexed by journal identifier (rvid)
     */
    public static function countByJournal(array $submissions): array
    {
        $counts = [];

        foreach ($submissions as $submission) {
            $counts[$submission['rvid']] = ($counts[$submission['rvid']] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * The years of first submission, from Stats::REF_YEAR
     *
     * @param list<Submission> $submissions
     * @return list<int> sorted
     */
    public static function years(array $submissions): array
    {
        $years = array_values(array_filter(
            array_unique(array_map(self::submissionYear(...), $submissions)),
            static fn(int $year): bool => $year >= Stats::REF_YEAR
        ));
        sort($years);

        return $years;
    }

    /**
     * The papers to list for the submissions: a submission is listed by the version it ends with, not by the latest
     * version of the paper (a refused version is not listed as the published version of its new submission)
     *
     * @param list<Submission> $submissions
     * @return list<array{paperid: int, docid: int}>
     */
    public static function papers(array $submissions): array
    {
        $papers = array_map(static fn(array $submission): array => ['paperid' => $submission['paperid'], 'docid' => $submission['docid']], $submissions);
        usort($papers, static fn(array $a, array $b): int => $a['docid'] <=> $b['docid']);

        return $papers;
    }

    /**
     * @param array{firstSubmissionDate: string} $submission
     */
    private static function submissionYear(array $submission): int
    {
        return (int)substr($submission['firstSubmissionDate'], 0, 4);
    }

    /**
     * @return callable(Submission): (int|null)
     */
    private static function yearOf(string $category): callable
    {
        return match ($category) {
            self::ACCEPTED => static fn(array $submission): ?int => self::dateYear($submission['acceptanceDate']),
            self::REFUSED => static fn(array $submission): ?int => self::dateYear($submission['refusalDate']),
            default => self::submissionYear(...),
        };
    }

    private static function dateYear(?string $date): ?int
    {
        return $date === null || $date === '' ? null : (int)substr($date, 0, 4);
    }

    /**
     * @param list<Submission> $submissions
     * @param array<int|string> $years
     * @param callable(Submission): (int|null) $yearOf
     * @return list<Submission>
     */
    private static function inYears(array $submissions, array $years, callable $yearOf): array
    {
        if ($years === []) {
            return $submissions;
        }

        $years = array_map('intval', $years);

        return array_values(array_filter($submissions, static fn(array $submission): bool => in_array($yearOf($submission), $years, true)));
    }
}
