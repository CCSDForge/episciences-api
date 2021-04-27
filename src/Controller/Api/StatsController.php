<?php

namespace App\Controller\Api;
use App\Repository\Main\PapersRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;


class StatsController extends AbstractController
{

    /**
     * @param Request $request
     * @param PapersRepository $papersRepository
     * @param  $data
     * @return mixed
     */
    public function __invoke(Request $request, PapersRepository $papersRepository, $data)
    {

        return $data;
        $rvId = (int)$request->get('id');
        $year = $request->get('year');

        $stats = [
            'Submissions' => [
                'Total' => $papersRepository->getNbSubmissionsByReview($rvId, $year),
                'details' => $papersRepository->getNbSubmissionsDetailsByReview($rvId, $year)
            ]
        ];

        $data->setStats($stats);
        return $data;

    }
}
