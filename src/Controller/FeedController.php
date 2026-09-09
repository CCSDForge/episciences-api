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
    public function __construct(private readonly \App\Service\Solr\SolrFeedService $feedService, private readonly \Doctrine\ORM\EntityManagerInterface $entityManager)
    {
    }
    /**
     * @throws ResourceNotFoundException
     */
    public function __invoke(Request $request): Response
    {
        $code = (string) $request->attributes->get('code');

        $format = str_contains($request->getPathInfo(), '/atom/') ? 'atom' : 'rss';

        $journal = $this->entityManager->getRepository(Review::class)->getJournalByIdentifier($code);

        if (!$journal) {
            throw new ResourceNotFoundException(sprintf('Oops! Feed cannot be generated: not found Journal %s', $code));
        }

        $feed = $this->feedService->setJournal($journal)->getSolrFeed($format);

        return new Response($feed->export($format), Response::HTTP_OK, ['Content-Type' => 'text/xml']);
    }

}