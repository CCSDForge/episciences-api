<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\ArrayPaginator;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Review;
use App\Repository\ReviewRepository;
use App\Resource\Journal;
use App\Service\JournalMapper;
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
        private readonly JournalMapper $journalMapper,
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
            $response[] = $this->journalMapper->toJournal($journal, $number);
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
                'rvid',
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
                    (string) $journal->getRvid(),
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
