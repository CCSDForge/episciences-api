<?php


namespace App\Controller;

use ApiPlatform\State\Pagination\ArrayPaginator;
use App\Entity\IndexingDatabase;
use App\Entity\Review;
use App\Exception\ResourceNotFoundException;
use App\Repository\IndexingDatabaseRepository;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

class IndexingDatabaseController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    )
    {
    }

    /**
     * @throws ResourceNotFoundException
     */
    public function __invoke(?Request $request = null): ArrayPaginator
    {
        $indexingDatabases = [];
        $page = 1;
        $itemsPerPage = 30;
        $pagination = true;
        $firstResult = 0;

        if ($request instanceof Request) {
            $code = $request->attributes->get('code');

            if ($code) {
                /** @var ReviewRepository $reviewRepo */
                $reviewRepo = $this->entityManager->getRepository(Review::class);
                $journal = $reviewRepo->getJournalByIdentifier($code);

                if (!$journal) {
                    throw new ResourceNotFoundException(sprintf('Journal not found: %s', $code));
                }

                /** @var IndexingDatabaseRepository $indexingDbRepo */
                $indexingDbRepo = $this->entityManager->getRepository(IndexingDatabase::class);
                $indexingDatabases = $indexingDbRepo->findByReviewId($journal->getRvid());

                // The page is validated (400) and checked against the last page (404) by RequestedPageSubscriber;
                // only itemsPerPage needs a local floor (the subscriber does not guard it).
                $page = $request->query->getInt('page', 1);
                $itemsPerPage = max(1, $request->query->getInt('itemsPerPage', 30));
                $pagination = filter_var($request->query->get('pagination', true), FILTER_VALIDATE_BOOLEAN);
            }
        }

        if ($pagination) {
            $firstResult = ($page - 1) * $itemsPerPage;
        }

        return new ArrayPaginator($indexingDatabases, $firstResult, $itemsPerPage);
    }
}
