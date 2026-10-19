<?php

namespace App\Service\Solr;

use ApiPlatform\Metadata\Exception\RuntimeException;
use App\Entity\Review;
use JsonException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SolrAuthorService extends AbstractSolrService
{
    public function __construct(
        HttpClientInterface $client,
        LoggerInterface $logger,
        ParameterBagInterface $parameters,
        private readonly SolrFacetService $facetService,
        ?Review $journal = null
    ) {
        parent::__construct($client, $logger, $parameters, $journal);
    }

    /**
     * The papers of an author, only the requested page.
     *
     * @param int $start first document (Solr "start"), page-based: ($page - 1) * $rows
     * @param int $rows number of documents of the page
     * @return array decoded Solr response ('response' => ['numFound' => int, 'docs' => list])
     */
    public function getSolrAuthorsByFullName(string $fullName, int $start = 0, int $rows = SolrConstants::SOLR_MAX_RETURNED_FACETS_RESULTS): array
    {
        try {
            $response = $this->client->request('GET', $this->buildAuthorsByFullNameQuery($fullName, $start, $rows));

            return json_decode(
                $response->getContent(),
                true,
                512,
                JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
            );
        } catch (JsonException|TransportExceptionInterface|ClientExceptionInterface|RedirectionExceptionInterface|ServerExceptionInterface $e) {
            $this->logger->critical($e->getMessage());
            throw new RuntimeException('Oops! An error occurred', $e->getCode(), $e);
        }
    }

    public function getCountArticlesByAuthorsFirstLetter(): array
    {
        $result = $this->facetService
            ->setJournal($this->journal)
            ->getSolrFacet(['facetFieldName' => 'authorFirstLetters_s', 'minCount' => 0]);

        return $result + [SolrConstants::SOLR_OTHERS_PREFIX => 0];
    }

    private function buildAuthorsByFullNameQuery(string $fullName, int $start, int $rows): string
    {
        $params = [
            'q' => 'author_fullname_t:' . addcslashes($fullName, '+-&|!(){}[]^"~*?:\\/'),
            'q.op' => 'AND',
            // Solr returns 10 documents by default: page the query explicitly
            'start' => max(0, $start),
            'rows' => min(max(1, $rows), SolrConstants::SOLR_MAX_RETURNED_FACETS_RESULTS),
            'indent' => 'false',
        ];

        return $this->buildSolrUrl(self::SOLR_SELECT_ENDPOINT, $params, $this->getJournalFilter());
    }
}
