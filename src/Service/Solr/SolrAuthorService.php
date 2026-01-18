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

    public function getSolrAuthorsByFullName(string $fullName): array
    {
        try {
            $response = $this->client->request('GET', $this->buildAuthorsByFullNameQuery($fullName));

            return json_decode(
                $response->getContent(),
                true,
                512,
                JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
            );
        } catch (JsonException|TransportExceptionInterface|ClientExceptionInterface|RedirectionExceptionInterface|ServerExceptionInterface $e) {
            $this->logger->critical($e->getMessage());
            throw new RuntimeException('Oops! An error occurred');
        }
    }

    public function getCountArticlesByAuthorsFirstLetter(): array
    {
        $result = $this->facetService
            ->setJournal($this->journal)
            ->getSolrFacet(['facetFieldName' => 'authorFirstLetters_s', 'minCount' => 0]);

        return $result + [SolrConstants::SOLR_OTHERS_PREFIX => 0];
    }

    private function buildAuthorsByFullNameQuery(string $fullName): string
    {
        $params = [
            'q' => 'author_fullname_t:' . urlencode($fullName),
            'q.op' => 'AND',
            'indent' => 'false',
        ];

        return $this->buildSolrUrl(self::SOLR_SELECT_ENDPOINT, $params, $this->getJournalFilter());
    }
}
