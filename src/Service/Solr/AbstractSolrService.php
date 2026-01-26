<?php

namespace App\Service\Solr;

use App\Entity\Review;
use App\Traits\ToolsTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

abstract class AbstractSolrService
{
    use ToolsTrait;

    protected const SOLR_SELECT_ENDPOINT = '/select/';

    public function __construct(
        protected readonly HttpClientInterface $client,
        protected readonly LoggerInterface $logger,
        protected readonly ParameterBagInterface $parameters,
        protected ?Review $journal = null
    ) {
    }

    public function getClient(): HttpClientInterface
    {
        return $this->client;
    }

    public function getJournal(): ?Review
    {
        return $this->journal;
    }

    public function setJournal(?Review $journal = null): static
    {
        $this->journal = $journal;
        return $this;
    }

    protected function buildSolrUrl(string $endpoint, array $params, array $filters = []): string
    {
        $baseUrl = $this->parameters->get('app.solr.host') . $endpoint;
        $queryString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        foreach ($filters as $filter) {
            $queryString .= '&fq=' . urlencode($filter);
        }

        return $baseUrl . '?' . $queryString;
    }

    protected function getJournalFilter(): array
    {
        if ($this->journal === null) {
            return [];
        }

        return ['revue_id_i:' . $this->journal->getRvid()];
    }
}
