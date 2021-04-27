<?php

namespace App\Controller\Api;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Paginator;
use App\Entity\Main\Review;
use App\Repository\Main\PapersRepository;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;


class allStatsController extends AbstractController
{
    /**
     * @param Request $request
     * @param PapersRepository $papersRepository
     * @param Paginator $data
     * @return Paginator
     * @throws Exception
     */
    public function __invoke(Request $request, PapersRepository $papersRepository, $data): Paginator
    {
        $year = $request->get('year');

            $iterator = $data->getIterator();
            /** @var Review $review */
            foreach ($iterator as $review){

                $stats = [
                    'Submissions' => [
                        'Total' => $papersRepository->getNbSubmissionsByReview($review->getRvid()),
                        'details' => $papersRepository->getNbSubmissionsDetailsByReview($review->getRvid(), $year)
                    ]
                ];

                $review->setStats($stats);
            }

        return $data;
    }
}
