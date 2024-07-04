<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\AppConstants;
use App\Entity\Paper;
use App\Entity\Review;
use App\Exception\ResourceNotFoundException;
use App\Resource\Statistic;

class StatisticStateProvider extends AbstractStateDataProvider implements ProviderInterface
{
    /**
     * @throws ResourceNotFoundException
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {


        $currentFilters = [];

        $filters = $context['filters'] ?? [];
        $code = $filters['code'] ?? null;

        if ($code) {

            $journal = $this->entityManagerInterface->getRepository(Review::class)->findOneBy(['code' => $code]);
            if (!$journal) {
                throw new ResourceNotFoundException(sprintf('Oops! not found Journal %s', $code));
            }

            $currentFilters['rvid'] = $journal->getRvid();
        }

        $dictionary = array_flip(Paper::STATUS_DICTIONARY);

        $status = isset($filters['status']) ? array_unique((array)$filters['status']) : [];
        $years = isset($filters[AppConstants::YEAR_PARAM]) ? array_unique((array)$filters[AppConstants::YEAR_PARAM]): null;
        $flag = $filters['flag'] ?? null;
        $startAfterDate = $filters['startAfterDate'] ?? null;

        foreach ($status as $currentStatus) {
            $toLowerStatus = strtolower($currentStatus);
            if(!array_key_exists($toLowerStatus, $dictionary)){
                throw new ResourceNotFoundException(sprintf('Oops! invalid status: %s', $currentStatus));
            }

            $currentFilters['status'][] = $dictionary[$toLowerStatus];
        }

        if($years){
            $currentFilters[AppConstants::YEAR_PARAM] = $years;
        }

        if($flag){
            $currentFilters['flag'] = $flag;
        }

        if($startAfterDate) {
            $currentFilters['startAfterDate'] = $startAfterDate;
        }

        $nbSubmissionsQuery  = $this->entityManagerInterface->getRepository(Paper::class)->submissionsQuery(['is' => $currentFilters])->getQuery();

        $result = (int)$nbSubmissionsQuery->getSingleScalarResult();

        return (new Statistic())->setName('nb-submissions')->setValue($result);

    }
}
