<?php

namespace App\Service\Solr;

use ApiPlatform\Metadata\Exception\RuntimeException;
use App\Entity\Review;
use App\Resource\Rss;
use JsonException;
use Laminas\Feed\Writer\Entry;
use Laminas\Feed\Writer\Feed;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SolrFeedService extends AbstractSolrService
{
    public function __construct(
        HttpClientInterface $client,
        LoggerInterface $logger,
        ParameterBagInterface $parameters,
        private readonly ?RequestStack $requestStack = null,
        ?Review $journal = null
    ) {
        parent::__construct($client, $logger, $parameters, $journal);
    }

    private const FEED_FIELDS = 'paper_title_t,abstract_t,author_fullname_s,revue_code_t,publication_date_tdate,keyword_t,revue_title_s,doi_s,es_doc_url_s,paperid';
    private const DOI_URL_PREFIX = 'https://doi.org/';

    public function getSolrFeed(string $format = 'rss'): Feed
    {
        try {
            $response = $this->client->request('GET', $this->buildFeedRssQuery());
            $responseArray = json_decode(
                $response->getContent(),
                true,
                512,
                JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
            );

            return $this->processSolrFeed($responseArray, $format);
        } catch (JsonException|TransportExceptionInterface|ClientExceptionInterface|RedirectionExceptionInterface|ServerExceptionInterface $e) {
            $this->logger->critical($e->getMessage());
            throw new RuntimeException('Oops! Feed cannot be generated: An error occurred');
        }
    }

    public function processSolrFeed(array $responseArray, string $format = 'rss'): Feed
    {
        $baseUrl = $this->requestStack?->getCurrentRequest()?->getSchemeAndHttpHost();
        $feed = (new Rss())
            ->setReview($this->journal)
            ->setBaseUrl($baseUrl)
            ->setFeedType($format)
            ->getFeed();
        $groups = $responseArray['grouped']['revue_title_s']['groups'] ?? [];

        foreach ($groups as $group) {
            $journalName = $group['groupValue'];
            $docs = $group['doclist']['docs'] ?? [];

            foreach ($docs as $doc) {
                $feed->addEntry($this->createFeedEntry($feed, $doc, $journalName));
            }
        }

        return $feed;
    }

    private function buildFeedRssQuery(): string
    {
        $params = [
            'indent' => 'true',
            'q' => '*:*',
            'group' => 'true',
            'group.field' => 'revue_title_s',
            'group.limit' => 30,
            'fl' => self::FEED_FIELDS,
            'sort' => 'publication_date_tdate desc',
        ];

        return $this->buildSolrUrl(self::SOLR_SELECT_ENDPOINT, $params, $this->getJournalFilter());
    }

    private function createFeedEntry(Feed $feed, array $doc, string $journalName): Entry
    {
        $entry = $feed->createEntry();

        $entry->setLink($this->buildDocumentLink($doc));
        $entry->setTitle($doc['paper_title_t'][0] ?? '');
        $entry->setDescription($doc['abstract_t'][0] ?? '...');

        foreach ($doc['author_fullname_s'] ?? [] as $author) {
            $entry->addAuthor([SolrConstants::SOLR_FACET_NAME => $author]);
        }

        $entry->addCategory(['term' => $journalName]);

        foreach ($doc['keyword_t'] ?? [] as $keyword) {
            $entry->addCategory(['term' => $keyword]);
        }

        $publicationDate = strtotime($doc['publication_date_tdate'] ?? 'now');
        $entry->setDateModified($publicationDate);
        $entry->setDateCreated($publicationDate);

        return $entry;
    }

    private function buildDocumentLink(array $doc): string
    {
        if (!empty($doc['doi_s'])) {
            return self::DOI_URL_PREFIX . $doc['doi_s'];
        }

        return $doc['es_doc_url_s'] ?? '';
    }
}
