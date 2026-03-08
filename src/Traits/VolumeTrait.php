<?php

namespace App\Traits;

use App\Entity\Paper;
use Doctrine\ORM\QueryBuilder;

trait VolumeTrait
{
    use QueryTrait;

    final public function addWhere(?int $rvId, QueryBuilder $qb, bool $strictlyPublished, int|array|null $ids): void
    {
        if ($rvId) {
            $qb
                ->andWhere("p.rvid = :rvId")
                ->setParameter('rvId', $rvId);
        }

        if ($strictlyPublished) {
            $qb->andWhere('p.status = :status')
                ->setParameter('status', Paper::STATUS_PUBLISHED);
        }

        if ($ids) {

            if (is_int($ids)) {
                $ids = (array)$ids;
            }

            $this->andOrExp($qb, sprintf('%s.vid', $qb->getAllAliases()[0]), $ids);
        }
    }

}
