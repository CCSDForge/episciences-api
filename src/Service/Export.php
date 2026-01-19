<?php

namespace App\Service;

use ApiPlatform\Exception\RuntimeException;
use App\Service\Solr\AbstractSolrService;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class Export extends AbstractSolrService
{
    public const HEADERS_FORMATS = [
        self::CSL_FORMAT => 'application/json',
        self::BIBTEX_FORMAT => 'text/plain',
        self::OPENAIRE_FORMAT => 'text/xml',
        self::CROSSREF_FORMAT => 'text/xml',
        self::DOAJ_FORMAT => 'text/xml',
        self::ZBJATS_FORMAT => 'text/xml',
        self::TEI_FORMAT => 'text/xml',
        self::DC_FORMAT => 'text/xml',
        self::JSON_FORMAT => 'application/json'
    ];

    public const SOLR_CSL_PREFIX = 'doc_';
    public const TEI_FORMAT = 'tei';
    public const DC_FORMAT = 'dc';
    public const CROSSREF_FORMAT = 'crossref';
    public const ZBJATS_FORMAT = 'zbjats';
    public const DOAJ_FORMAT = 'doaj';
    public const BIBTEX_FORMAT = 'bibtex';
    public const CSL_FORMAT = 'csl';
    public const OPENAIRE_FORMAT = 'openaire';
    public const JSON_FORMAT = 'json';


    public const AVAILABLE_FORMATS = [
        self::TEI_FORMAT,
        self::DC_FORMAT,
        self::CROSSREF_FORMAT,
        self::ZBJATS_FORMAT,
        self::DOAJ_FORMAT,
        self::BIBTEX_FORMAT,
        self::CSL_FORMAT,
        self::OPENAIRE_FORMAT,
        self::JSON_FORMAT,
    ];


    public function exportToFormatQuery(int $docId, string $format = self::AVAILABLE_FORMATS[self::CSL_FORMAT]): string
    {

        $journal = $this->getJournal();

        $solrQuery = sprintf('%s/select/?', $this->parameters->get('app.solr.host'));

        if ($format) {
            $solrQuery .= sprintf('fl=%s%s', self::SOLR_CSL_PREFIX, $format);
        }


        if ($journal) {
            $solrQuery .= '&fq=revue_code_t:' . $journal->getCode();
        }

        $solrQuery .= '&indent=true&q.op=OR';

        if ($docId) {
            $solrQuery .= sprintf('&q=docid:%s paperid:%s', $docId, $docId);
        }

        return $solrQuery;

    }


    public function getSolrCSLByFormat(int $docId, string $format = self::AVAILABLE_FORMATS[self::CSL_FORMAT])
    {
        try {
            $response = $this->client->request(
                'GET',
                $this->exportToFormatQuery($docId, $format)
            );
            $response = $response->getContent();
            $responseToArray = json_decode($response, true, 512, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            return $responseToArray['response']['docs'][0][sprintf('%s%s', self::SOLR_CSL_PREFIX, $format)] ?? null;

        } catch (\JsonException|TransportExceptionInterface|ClientExceptionInterface|RedirectionExceptionInterface|ServerExceptionInterface $e) {
            $this->logger->critical($e->getMessage());
            throw new RuntimeException('Oops! Export cannot be generated: An error occurred');

        }

    }

}