<?php

namespace App\State;

use ApiPlatform\Doctrine\Orm\Paginator;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\ProviderInterface;
use App\Doctrine\UnpaginatedCollectionLimitExtension;
use App\Entity\ReviewSetting;
use App\Entity\Volume;
use App\Exception\ResourceNotFoundException;
use App\Repository\VolumeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class VolumeStateProvider extends AuthenticationStateProvider implements ProviderInterface
{
    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        Pagination $pagination,
        Security $security,
        private readonly UnpaginatedCollectionLimitExtension $unpaginatedCollectionLimit
    )
    {
        parent::__construct($entityManager, $logger, $pagination, $security);
    }

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
        $filters = $context['filters'] ?? [];

        if ($operation instanceof CollectionOperationInterface) {

            if ($iPaginationEnabled) {
                $doctrinePaginator = $volumeRepo->listPaginator($page, $limit, $filters);
                return new Paginator($doctrinePaginator);
            }

            // Capped like the Doctrine collections (?pagination=false), see UnpaginatedCollectionLimitExtension
            return $this->unpaginatedCollectionLimit->getResult($volumeRepo->listQuery($filters), Volume::class, $operation, $context);
        }

        // return a single volume
        $vid = $uriVariables['vid'] ?? null;

        if (!$vid) {
            return null;
        }

        return $volumeRepo->findOneByWithContext(['vid' => $vid],null, $filters);
    }
}
