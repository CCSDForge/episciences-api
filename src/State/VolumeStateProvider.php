<?php

namespace App\State;

use ApiPlatform\Doctrine\Orm\Paginator;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\AppConstants;
use App\Entity\Review;
use App\Entity\Volume;
use App\Exception\ResourceNotFoundException;
use App\Repository\VolumeRepository;

final class VolumeStateProvider extends AbstractStateDataProvider implements ProviderInterface
{

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {


        try {
            $this->checkAndProcessFilters($context);
        } catch (ResourceNotFoundException $e) {
            $this->logger->critical($e->getMessage());
        }

        /** @var Review $currentJournal */
        $currentJournal = $context[self::CONTEXT_JOURNAL_KEY] ?? null;

        if ($operation->getClass() !== Volume::class) {
            return null;
        }

        // Récupération des paramètres de pagination (page, limit)
        [$page, , $limit] = $this->pagination->getPagination($operation, $context);

        $iPaginationEnabled = $this->pagination->isEnabled($operation, $context);

        /** @var VolumeRepository $volumeRepo */
        $volumeRepo = $this->entityManager->getRepository(Volume::class);

        if ($operation instanceof CollectionOperationInterface) {
            $rvId = $currentJournal?->getRvid();

            if ($iPaginationEnabled) {
                $doctrinePaginator = $volumeRepo->listPaginator($page, $limit, $rvId);
                return new Paginator($doctrinePaginator);
            }

            return $volumeRepo->listQuery($rvId)->getQuery()->getResult();
        }

        // return a single volume
        $vid = $uriVariables['vid'] ?? null;

        if (!$vid) {
            return null;
        }

        return $volumeRepo->findOneBy(['vid' => $vid]);
    }
}
