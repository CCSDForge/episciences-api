<?php

namespace App\Controller;

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
        $code = (string) $request->get('code');

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

        if ($code){

            $journal = $entityManager->getRepository(Review::class)->getJournalByIdentifier($code);
            if (!$journal) {
                throw new ResourceNotFoundException(sprintf('Oops! CSL cannot be generated: not found Journal %s', $code ));
            }

        }


        $solrSrv->setJournal($journal);

        $export = $solrSrv->getSolrCSLByFormat($docId, $format);

        if(!$export){
            throw new ResourceNotFoundException(sprintf('Oops! CSL cannot be generated: not found document %s', $docId ));
        }

        return new Response($export, 200, ['Content-Type' => Export::HEADERS_FORMATS[$format]]);

    }
}