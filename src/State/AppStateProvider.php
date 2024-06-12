<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\ArrayPaginator;
use App\Entity\Review;
use App\Repository\YearsInterface;
use Doctrine\ORM\EntityManagerInterface;

use RuntimeException;

class AppStateProvider
{

    private EntityManagerInterface $entityManager;


    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;

    }

    protected function getCollection(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {

        $code = $context['filters']['rvcode'] ?? null;

        if ($code === '{code}') {
            $code = null;
        }

        $journal = null;

        $isPaginationEnabled = !isset($context['filters']['pagination']) || filter_var($context['filters']['pagination'], FILTER_VALIDATE_BOOLEAN);
        $page = $filter['page'] ?? 1;
        $firstResult = 0;
        $resourceName = $operation->getClass();

        $maxResults = $operation->getPaginationMaximumItemsPerPage();

        if ($isPaginationEnabled) {
            $maxResults = $context['filters']['itemsPerPage'] ?? $maxResults;
            $firstResult = ($page - 1) * $maxResults;

        }

        if ($code) {

            $journal = $this->entityManager->getRepository(Review::class)->findOneBy(['code' => $code]);

            if (!$journal) {
                throw new RuntimeException(sprintf('Oops! not found Journal %s', $code));
            }

        }

        $repo = $this->entityManager->getRepository($resourceName);
        $qb = $repo->createQueryBuilder('o');

        if ($journal) {
            $qb->andWhere('o.rvcode= :rvCode')->setParameter('rvCode', $journal->getCode());
        }

        $oResult = array_map(static function ($value) {
            return (object)$value;
        }, $qb->getQuery()->getResult());

        try {
            if ((new \ReflectionClass($repo::class))->implementsInterface(YearsInterface::class)) {
                $oResult = array_merge($repo->getAvailableYears(), $oResult);
            }
        } catch (\ReflectionException $e) {
            throw new RuntimeException('Oops! An error occurred');
        }

        return new ArrayPaginator($oResult, $firstResult, $maxResults);
    }
}
