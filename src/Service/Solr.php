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
    private HttpClientInterface $client;
    private ?Review $journal;
    private LoggerInterface $logger;
    private ParameterBagInterface $parameters;

    public function __construct(HttpClientInterface $client, LoggerInterface $logger,  ParameterBagInterface $parameters)
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

}