<?php

namespace App\Service;

use ApiPlatform\Exception\RuntimeException;
use App\Entity\Review;
use App\Resource\Rss;
use Laminas\Feed\Writer\Feed;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;


class Solr
{
    public const SOLR_MAX_RETURNED_FACETS_RESULTS = 1000;
    public const SOLR_FACET_SEPARATOR = '_FacetSep_';
    private HttpClientInterface $client;
    private ?Review $journal;
    private LoggerInterface $logger;
    private ParameterBagInterface $parameters;

    public function __construct(HttpClientInterface $client, LoggerInterface $logger, ParameterBagInterface $parameters)
    {
        $this->client = $client;
        $this->logger = $logger;
        $this->parameters = $parameters;

    }

    private function getSolrFeedRssQuery(): string
    {
        $journal = $this->getJournal();

        $solrQuery = $this->parameters->get('app.solr.host') . '/select/?';
        $solrQuery .= 'indent=true&q=*:*&group=true&group.field=revue_title_s&group.limit=2&fl=paper_title_t,abstract_t,author_fullname_s,revue_code_t,publication_date_tdate,keyword_t,revue_title_s,doi_s,es_doc_url_s,paperid&sort=publication_date_tdate desc';

        if ($journal) {
            $solrQuery .= '&fq=revue_id_i:' . $journal->getRvid();
        }

        return $solrQuery;

    }

    private function getSolrFacetQuery(array $params = []): ?string
    {
        $facetFieldName = $params['facetFieldName'] ?? '';
        $facetLimit = $params['facetLimit'] ?? self::SOLR_MAX_RETURNED_FACETS_RESULTS;

        if (empty($facetFieldName)) {
            return null;
        }

        $journal = $this->getJournal();


        $letter = $params['letter'] ?? 'all';
        $sortType = $params['sortType'] ?? 'index';

        if ($sortType !== 'count') {
            $sortType = 'index';
        }

        $baseQueryString = $this->parameters->get('app.solr.host') . '/select/?';

        $baseQueryString .= 'q=*:*&rows=0&wt=phps&indent=false&facet=true&omitHeader=true&facet.limit=';
        $baseQueryString .= $facetLimit . '&facet.mincount=1&facet.field={!key=list}';
        $baseQueryString .= urlencode($facetFieldName);

        if ($journal) {
            $baseQueryString .= '&fq=revue_id_i:' . $journal->getRvid();
        }

        if ($letter !== 'all') {
            $baseQueryString .= '&facet.prefix=' . $letter;
        }

        $baseQueryString .= '&facet.sort=' . $sortType;


        return $baseQueryString;

    }


    public function getSolrFeedRss(): Feed
    {

        try {
            $response = $this->client->request(
                'GET',
                $this->getSolrFeedRssQuery()

            );
            $response = $response->getContent();
            $responseToArray = json_decode($response, true, 512, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

            return $this->processSolrFeed($responseToArray);


        } catch (\JsonException|TransportExceptionInterface|ClientExceptionInterface|RedirectionExceptionInterface|ServerExceptionInterface $e) {
            $this->logger->critical($e->getMessage());
            throw new RuntimeException('Oops! Feed cannot be generated: An error occurred');

        }

    }

    public function getSolrFacet(
        array $params = [
            'facetFieldName' => '',
            'facetLimit' => Solr::SOLR_MAX_RETURNED_FACETS_RESULTS,
            'letter' => 'A',
            'sortType' => 'index'

        ]): array
    {


        $query = $this->getSolrFacetQuery($params);

        if (!$query) {
            return [];
        }


        try {
            $response = $this->client->request(
                'GET',
                $query

            );
            $response = $response->getContent();
            $toArray = unserialize($response, ['allowed_classes' => false]);


            $list = $toArray['facet_counts']['facet_fields']['list'];

            if (!is_array($list)) {
                return [];
            }

            $result = [];

            foreach ($list as $name => $count) {
                $exploded = explode(self::SOLR_FACET_SEPARATOR, $name);
                if (count($exploded) > 1) {
                    $result[$exploded[0]]['name'] = $exploded[1];
                    $result[$exploded[0]]['count'] = $count;
                } else {
                    $result[$name] = $count;
                }
            }

        } catch (\JsonException|TransportExceptionInterface|ClientExceptionInterface|RedirectionExceptionInterface|ServerExceptionInterface $e) {
            $this->logger->critical($e->getMessage());
            throw new RuntimeException('Oops! An error occurred');

        }

        return $result;

    }

    /**
     * @return HttpClientInterface
     */
    public function getClient(): HttpClientInterface
    {
        return $this->client;
    }

    public function getJournal(): ?Review
    {
        return $this->journal;
    }

    public function setJournal(Review $journal = null): self
    {
        $this->journal = $journal;
        return $this;

    }

    public function processSolrFeed(array $responseToArray): Feed
    {

        $feed = (new Rss())->setReview($this->getJournal())->getFeed();

        foreach ($responseToArray["grouped"]["revue_title_s"]["groups"] as $entry) {

            $journal = $entry["groupValue"];

            foreach ($entry['doclist']["docs"] as $docEntry) {

                $entry = $feed->createEntry();

                if (!empty($docEntry['doi_s'])) {
                    $link = sprintf('https://doi.org/%s', $docEntry['doi_s']);
                } else {
                    $link = $docEntry['es_doc_url_s'];
                }

                $entry->setLink($link);

                $entry->setTitle($docEntry['paper_title_t'][0]);
                if (empty($docEntry['abstract_t'][0])) {
                    $abstract = '...';
                } else {
                    $abstract = $docEntry['abstract_t'][0];
                }

                $entry->setDescription($abstract);


                foreach ($docEntry['author_fullname_s'] as $oneAuthor) {
                    $entry->addAuthor(['name' => $oneAuthor]);
                }

                $entry->addCategory(['term' => $journal]);

                foreach ($docEntry['keyword_t'] as $oneKeyword) {
                    $entry->addCategory(['term' => $oneKeyword]);
                }

                $publicationDate = strtotime($docEntry['publication_date_tdate']);
                $entry->setDateModified($publicationDate);
                $entry->setDateCreated($publicationDate);

                $feed->addEntry($entry);
            }


        }

        return $feed;

    }

    public function getSolrAuthorsByFullName(string $fullName): array
    {

        try {
            $response = $this->client->request(
                'GET',
                $this->getSolrAuthorsByFullNameQuery($fullName)

            );
            $response = $response->getContent();
            $responseToArray = json_decode($response, true, 512, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

            return $responseToArray;


        } catch (\JsonException|TransportExceptionInterface|ClientExceptionInterface|RedirectionExceptionInterface|ServerExceptionInterface $e) {
            $this->logger->critical($e->getMessage());
            throw new RuntimeException('Oops! Feed cannot be generated: An error occurred');

        }

    }

    private function getSolrAuthorsByFullNameQuery(string $fullName): string
    {
        $journal = $this->getJournal();
        $solrQuery = $this->parameters->get('app.solr.host') . '/query/?q=author_fullname_t:';
        $solrQuery .= sprintf('%s&q.op=OR&indent=false', urlencode($fullName));

        if ($journal) {
            $solrQuery .= '&fq=revue_id_i:' . $journal->getRvid();
        }

        return $solrQuery;

    }



}