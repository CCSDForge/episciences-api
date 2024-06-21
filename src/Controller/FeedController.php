<?php

namespace App\Controller;

use ApiPlatform\Exception\RuntimeException;
use App\Entity\Review;
use App\Exception\ResourceNotFoundException;
use App\Service\Solr;
use App\Traits\CheckExistingResourceTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;


class FeedController extends AbstractController
{
    public function __invoke(Request $request, Solr $solrSrv, EntityManagerInterface $entityManager): Response
    {

        $code = (string) $request->get('code');

        $journal = $entityManager->getRepository(Review::class)->findOneBy(['code' => $code]);

        if (!$journal) {
            throw new ResourceNotFoundException(sprintf('Oops! Feed cannot be generated: not found Journal %s', $code ));
        }

        $solrSrv->setJournal($journal);

        $feed = $solrSrv->getSolrFeedRss();

        return new Response($feed->export('rss'), 200, ['Content-Type' => 'text/xml']);

    }

}