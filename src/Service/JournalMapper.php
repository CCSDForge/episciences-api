<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Review;
use App\Entity\ReviewSetting;
use App\Resource\Journal;

/**
 * Builds the public Journal resource out of a Review journal, filling the derived
 * fields (ISSN / EISSN, address, accepted repositories) from its settings. Shared by
 * the collection provider (GET /api/journals/, with its sequential counter) and the
 * item provider (GET /api/journals/{code}, which deliberately omits `number` as it
 * is a duplicate of `rvid` on a single journal).
 */
class JournalMapper
{
    public function __construct(private readonly MetadataSources $metadataSources)
    {
    }

    /**
     * Builds a Journal resource. The `number` is the sequential position in the
     * collection (a 1-based counter); the item provider does not pass one, so the
     * field is left unset and omitted from the item response.
     */
    public function toJournal(Review $journal, ?int $number = null): Journal
    {
        $resource = new Journal();
        if ($number !== null) {
            $resource->setNumber($number);
        }
        $resource->setRvid($journal->getRvid());
        $resource->setCode($journal->getCode());
        $resource->setTitle($journal->getName());
        $resource->setSubtitle($journal->getSubtitle());
        $resource->setIssn($this->formatIssn((string) $journal->getSetting(ReviewSetting::SETTING_ISSN_PRINT)));
        $resource->setEissn($this->formatIssn((string) $journal->getSetting(ReviewSetting::SETTING_ISSN)));
        $resource->setAddress(sprintf('https://%s.%s', $journal->getCode(), Journal::DOMAIN));

        $repositories = $this->acceptedRepositories($journal);
        $repositories = $this->withoutAutomaticHal($journal, $repositories);
        // The journal is always returned, even when no repository is accepted.
        $resource->setAcceptedRepositories($repositories);

        return $resource;
    }

    /**
     * The labels of the accepted repositories of the journal (aligned with the GPL
     * reference list), indexed by repository id.
     *
     * @param Review $journal
     * @return array<int, string>
     */
    private function acceptedRepositories(Review $journal): array
    {
        $repositories = [];
        $setting = $journal->getSetting(ReviewSetting::SETTING_REPOSITORIES);
        if ($setting === null || $setting === '') {
            return $repositories;
        }

        $repoIds = json_decode($setting, true);
        if (!is_array($repoIds)) {
            return $repositories;
        }

        foreach ($repoIds as $repoId) {
            $repoId = (int) $repoId;
            $label = $this->metadataSources->getLabel($repoId);
            if ($label !== '') {
                $repositories[$repoId] = $label;
            }
        }

        return $repositories;
    }

    /**
     * A journal disabling the automatic transfer to HAL does not expose HAL as an
     * accepted repository. HAL repositories are detected from their label.
     *
     * @param Review $journal
     * @param array<int, string> $repositories
     * @return array<int, string>
     */
    private function withoutAutomaticHal(Review $journal, array $repositories): array
    {
        $disableAutomaticTransfer = $journal->getSetting(ReviewSetting::SETTING_DISABLE_AUTOMATIC_TRANSFER);
        if (!$disableAutomaticTransfer) {
            return $repositories;
        }

        return array_filter($repositories, static fn(string $label): bool => !str_contains($label, 'HAL'));
    }

    /**
     * Formats an ISSN to the canonical "XXXX-XXXX" shape, replicating the GPL FormatIssn
     * helper: an 8-digit string is split, anything else is returned as-is and an empty
     * value stays empty.
     */
    private function formatIssn(string $issn): string
    {
        if ($issn === '') {
            return '';
        }

        if (strlen($issn) === 8) {
            return substr($issn, 0, 4) . '-' . substr($issn, 4, 4);
        }

        return $issn;
    }
}
