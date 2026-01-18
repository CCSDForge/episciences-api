<?php

namespace App\Controller;

use App\Entity\Review;
use App\Exception\ResourceNotFoundException;
use App\Service\Solr\SolrFeedService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;


class FeedController extends AbstractController
{
    /**
     * @throws ResourceNotFoundException
     */
    public function __invoke(Request $request, SolrFeedService $feedService, EntityManagerInterface $entityManager): Response
    {
        $code = (string) $request->attributes->get('code');

        $format = str_contains($request->getPathInfo(), '/atom/') ? 'atom' : 'rss';

        $journal = $entityManager->getRepository(Review::class)->getJournalByIdentifier($code);

        if (!$journal) {
            throw new ResourceNotFoundException(sprintf('Oops! Feed cannot be generated: not found Journal %s', $code));
        }

        $feed = $feedService->setJournal($journal)->getSolrFeed($format);

        return new Response($feed->export($format), Response::HTTP_OK, ['Content-Type' => 'text/xml']);
    }

}