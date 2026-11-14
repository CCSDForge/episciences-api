<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\ArrayPaginator;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Review;
use App\Entity\ReviewSetting;
use App\Repository\ReviewRepository;
use App\Resource\Journal;
use App\Service\MetadataSources;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides the collection of active journals (GET /api/journals/).
 *
 * Aligned with the GPL reference implementation (journalsAction / findActiveJournals):
 * only the active journals are returned, enriched with derived fields (ISSN / EISSN,
 * address, accepted repositories). The `format` query parameter is honored: JSON family
 * formats and HTML/XML are served through the API Platform cycle (paginated), while the
 * `csv` format is streamed directly as a download.
 */
final class JournalCollectionProvider extends AuthenticationStateProvider implements ProviderInterface
{
    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        Pagination $pagination,
        Security $security,
        private readonly ReviewRepository $reviewRepository,
        private readonly MetadataSources $metadataSources,
        private readonly RequestStack $requestStack
    ) {
        parent::__construct($entityManager, $logger, $pagination, $security);
    }

    /**
     * @param Operation $operation
     * @param array $uriVariables
     * @param array $context
     * @return object|array|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation->getClass() !== Review::class) {
            return null;
        }

        // Normalize the `filters` from the request (page / pagination), like the other
        // state providers do before reading them.
        $this->checkAndProcessFilters($context);

        $journals = $this->reviewRepository->getActiveJournals();

        $response = [];
        $number = 0;
        foreach ($journals as $journal) {
            ++$number;
            $response[] = $this->toJournal($journal, $number);
        }

        $request = $this->requestStack->getCurrentRequest();
        $format = $request?->query->get('format') ?? 'jsonld';

        if ($format === 'csv') {
            return $this->buildCsvResponse($response);
        }

        // Drive the API Platform serialization from the explicit `format` query parameter.
        // The content negotiation would otherwise rely on the Accept header only.
        $request?->setRequestFormat($format);

        return $this->paginate($response, $context);
    }

    /**
     * Builds a Journal resource out of an active journal, filling the derived fields
     * (ISSN / EISSN, address, accepted repositories) from its settings.
     */
    private function toJournal(Review $journal, int $number): Journal
    {
        $resource = new Journal();
        $resource->setNumber($number);
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

    /**
     * Paginates the collection for the JSON family, HTML and XML formats served through
     * the API Platform cycle. The pagination parameters are read from the query filters,
     * following the StatisticStateProvider pattern.
     *
     * @param array<int, Journal> $response
     * @param array $context
     */
    private function paginate(array $response, array $context): \ApiPlatform\State\Pagination\ArrayPaginator
    {
        $isPaginationEnabled = $context['filters']['pagination'] ?? true;
        $page = max(1, (int) ($context['filters']['page'] ?? 1));

        /** @var Operation $operation */
        $operation = $context['operation'];
        $maxResults = $operation->getPaginationMaximumItemsPerPage() ?: 30;
        $firstResult = 0;

        if ($isPaginationEnabled) {
            $maxResults = (int) ($context['filters']['itemsPerPage'] ?? $maxResults);
            $firstResult = ($page - 1) * $maxResults;
        }

        $this->flagIfTruncated($context, count($response), $maxResults);

        $paginator = new ArrayPaginator($response, $firstResult, $maxResults);
        $this->checkSeekPosition($paginator);

        return $paginator;
    }

    /**
     * Streams the collection as a CSV download, with the download headers of the GPL
     * reference. The first line is a column-name header. Only the header (and no data
     * row) is written when there is no journal.
     *
     * @param array<int, Journal> $journals
     */
    private function buildCsvResponse(array $journals): StreamedResponse
    {
        $filename = sprintf('journals_%s.csv', date('Ymd'));

        $response = new StreamedResponse(static function () use ($journals): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'number',
                'code',
                'title',
                'subtitle',
                'issn',
                'eissn',
                'address',
                'acceptedRepositories',
            ], ',', '"', '\\');

            foreach ($journals as $journal) {
                fputcsv($handle, [
                    $journal->getNumber(),
                    (string) $journal->getCode(),
                    (string) $journal->getTitle(),
                    $journal->getSubtitle(),
                    $journal->getIssn(),
                    $journal->getEissn(),
                    $journal->getAddress(),
                    implode(';', array_values($journal->getAcceptedRepositories())),
                ], ',', '"', '\\');
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
