<?php

namespace App\State;

use ApiPlatform\Doctrine\Orm\Paginator;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Volume;
use App\Exception\ResourceNotFoundException;
use App\Repository\VolumeRepository;

final class VolumeStateProvider extends AuthenticationStateProvider implements ProviderInterface
{

    /**
     * @param Operation $operation
     * @param array $uriVariables
     * @param array $context
     * @return object|array|object[]|null
     * @throws ResourceNotFoundException
     */

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation->getClass() !== Volume::class) {
            return null;
        }

        $this->checkAndProcessFilters($context);

        $context['filters']['isGranted'] = $this->security->isGranted('ROLE_SECRETARY');

        // Récupération des paramètres de pagination (page, limit)
        [$page, , $limit] = $this->pagination->getPagination($operation, $context);

        $iPaginationEnabled = $this->pagination->isEnabled($operation, $context);

        /** @var VolumeRepository $volumeRepo */
        $volumeRepo = $this->entityManager->getRepository(Volume::class);

        if ($operation instanceof CollectionOperationInterface) {

            $filters = $context['filters'] ?? [];

            if ($iPaginationEnabled) {
                $doctrinePaginator = $volumeRepo->listPaginator($page, $limit, $filters);
                return new Paginator($doctrinePaginator);
            }

            return $volumeRepo->listQuery($filters)->getQuery()->getResult();
        }

        // return a single volume
        $vid = $uriVariables['vid'] ?? null;

        if (!$vid) {
            return null;
        }

        return $volumeRepo->findOneBy(['vid' => $vid]);
    }
}
