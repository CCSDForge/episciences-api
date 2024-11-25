<?php

namespace App\Controller;

use App\Entity\Paper;
use App\Entity\Review;
use App\Exception\ResourceNotFoundException;
use App\Service\Export;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ExportController extends AppAbstractController
{

    /**
     * @throws ResourceNotFoundException
     */
    public function __invoke(Request $request, Export $solrSrv, EntityManagerInterface $entityManager): Response
    {
        $journal = null;
        $code = (string)$request->get('code');

        $docId = (int)$request->attributes->get('docid');
        $format = (string)$request->attributes->get('format');

        if (!$docId) {
            throw new ResourceNotFoundException('Oops! Required field {docid} is not provided');
        }

        if (empty($format)) {
            throw new ResourceNotFoundException('Oops! Required field {format} is not provided');
        }

        if (!in_array($format, Export::AVAILABLE_FORMATS, true)) {
            $message = sprintf('Oops! invalid format: %s because the available values are [%s]', $format, implode(', ', Export::AVAILABLE_FORMATS));
            throw new ResourceNotFoundException($message);
        }

        if ($code) {

            $journal = $entityManager->getRepository(Review::class)->getJournalByIdentifier($code);
            if (!$journal) {
                throw new ResourceNotFoundException(sprintf('Oops! CSL cannot be generated: not found Journal %s', $code));
            }

        }

        $solrSrv->setJournal($journal);

        if ($format === Export::JSON_FORMAT) {

            $export = $entityManager->getRepository(Paper::class)->paperToJson($docId, $journal?->getRvid());

        } else {
            $export = $solrSrv->getSolrCSLByFormat($docId, $format);
        }

        if (!$export) {
            $codeMessage = $code ? sprintf(' in selected Journal {%s}', $code) : '';
            $specificToJsonMsg = $format === Export:: JSON_FORMAT ?  "article's document field is not null" : "the export format has been indexed correctly";
            throw new ResourceNotFoundException(sprintf("Oops! CSL cannot be generated (first see if %s): the document %s does not exist or has not yet been published%s",$specificToJsonMsg, $docId, $codeMessage));
        }

        return new Response($export, Response::HTTP_OK, ['Content-Type' => Export::HEADERS_FORMATS[$format]]);

    }
}