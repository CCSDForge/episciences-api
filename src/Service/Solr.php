<?php

namespace App\Service;

use ApiPlatform\Exception\RuntimeException;
use App\Entity\Review;
use App\Resource\Rss;
use App\Traits\ToolsTrait;
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
    use ToolsTrait;
    public const SOLR_MAX_RETURNED_FACETS_RESULTS = 10000;
    public const SOLR_FACET_SEPARATOR = '_FacetSep_';
    public const SOLR_OTHERS_FACET_SEPARATOR = 'Others_FacetSep_';
    public const SOLR_OTHERS_PREFIX = 'Others';
    public const SOLR_ALL_PREFIX = 'All';
    public const SOLR_INDEX = 'index';
    public const SOLR_COUNT = 'count';


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

        $minCount = $params['minCount'] ?? 1;

        if (empty($facetFieldName)) {
            return null;
        }

        $journal = $this->getJournal();

        if (isset($params['letter'])) {

            $letter = mb_ucfirst($params['letter']);

            if (!in_array($letter, $this->getLettersRange(), true)) {
                $letter = self::SOLR_ALL_PREFIX;
            }

        } else {
            $letter = self::SOLR_ALL_PREFIX;
        }

        if ($letter === self::SOLR_OTHERS_PREFIX) {
            $letter = self::SOLR_OTHERS_FACET_SEPARATOR;
        }


        $search = $params['search'] ?? '';
        $sortType = $params['sortType'] ?? self::SOLR_INDEX;

        if ($sortType !== 'count') {
            $sortType = 'index';
        }

        $baseQueryString = $this->parameters->get('app.solr.host') . '/select/?';

        $baseQueryString .= 'q=*:*&rows=0&wt=phps&indent=false&facet=true&omitHeader=true&facet.limit=';
        $baseQueryString .= sprintf('%s&facet.mincount=%s&facet.field={!key=list}', $facetLimit, $minCount);
        $baseQueryString .= urlencode($facetFieldName);

        if ($journal) {
            $baseQueryString .= '&fq=revue_id_i:' . $journal->getRvid();
        }

        if ($letter !== self::SOLR_ALL_PREFIX) {
            $baseQueryString .= '&facet.prefix=' . $letter;
        }

        if ($search !== '') {
            $baseQueryString .= '&facet.contains=' . $search;
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
            'sortType' => self::SOLR_INDEX,
            'search' => '',
            'minCount' => 1

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
                $name = str_replace(self::SOLR_OTHERS_FACET_SEPARATOR, '', $name);
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
            throw new RuntimeException('Oops! An error occurred');
        }

    }

    private function getSolrAuthorsByFullNameQuery(string $fullName): string
    {
        $field = 'author_fullname_t';
        $journal = $this->getJournal();
        $solrQuery = sprintf('%s/select/?q=%s:', $this->parameters->get('app.solr.host'), $field);
        $solrQuery .= sprintf('%s&q.op=OR&indent=false', urlencode($fullName));

        if ($journal) {
            $solrQuery .= '&fq=revue_id_i:' . $journal->getRvid();
        }

        return $solrQuery;

    }

    /**
     * Table of authors' 1st letters, with the number of articles for each letter
     * @return array
     */

    public function getCountArticlesByAuthorsFirstLetter(): array
    {
        $params = ['facetFieldName' => 'authorFirstLetters_s', 'minCount' => 0];
        $result = $this->setJournal($this->getJournal())->getSolrFacet($params);

        if(!isset($result[self::SOLR_OTHERS_PREFIX])){
            $result[self::SOLR_OTHERS_PREFIX] = 0;
        }

        return $result;
    }


    public function getLettersRange(): array
    {
        return array_merge(range('A', 'Z'), [self::SOLR_OTHERS_PREFIX, self::SOLR_ALL_PREFIX]);
    }

}