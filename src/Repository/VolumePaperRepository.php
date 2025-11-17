<?php

namespace App\Repository;

use App\Entity\Paper;
use App\Entity\VolumePaper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VolumePaper>
 */
class VolumePaperRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly PapersRepository $paperRepository)
    {
        parent::__construct($registry, VolumePaper::class);
    }

    public function getPapersInSecondaryVolumeWithoutPositionQuery(int $vid = null): QueryBuilder
    {

        $qb = $this->getEntityManager()->createQueryBuilder();

        $qb =
            $qb->select('vp')
                ->from(VolumePaper::class, 'vp')
                ->innerJoin(Paper::class, 'p', Join::WITH, 'vp.docid = p.docid')
                ->groupBy('vp.vid')
                ->groupBy('vp.docid');

        if ($vid !== null) {
            $qb->andWhere('vp.vid = :vid')->setParameter('vid', $vid);
        }

        return $qb;

    }

    public function getPapersFromSecondaryVolume(int $vid = null): ArrayCollection
    {

        $collection = new ArrayCollection();

        $qb = $this->getPapersInSecondaryVolumeWithoutPositionQuery($vid);
        $result = $qb->getQuery()->getResult();

        if (!empty($result)) {

            /** @var VolumePaper $secondaryVolume */


            foreach ($result as $secondaryVolume) {
                /** @var Paper $paper */

                $paper = $this->paperRepository->fetchPartialByDocId($secondaryVolume->getDocid());

                if ($paper && !$collection->contains($paper)) {
                    $collection->add($paper);

                }

            }
        }

        return $collection;
    }

    public function getNoEmptySecondaryVolumes(int $rvId = null, bool $strictlyPublished = true) : QueryBuilder {

        $qb = $this->createQueryBuilder('sv');
        $qb->select('sv.vid')
            ->distinct()
            ->innerJoin(Paper::class, 'p', Join::WITH, 'sv.docid = p.docid');

        if($rvId) {
            $qb
                ->andWhere("p.rvid = :rvId")
                ->setParameter('rvId', $rvId);
        }

        if ($strictlyPublished) {
            $qb->andWhere('p.status = :status')
                ->setParameter('status', Paper::STATUS_PUBLISHED);
        }


        return $qb;
    }
}
