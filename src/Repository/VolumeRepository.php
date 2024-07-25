<?php

namespace App\Repository;

use App\Entity\Section;
use App\Entity\Volume;
use App\Traits\QueryTrait;
use App\Traits\ToolsTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Volume>
 */
class VolumeRepository extends ServiceEntityRepository implements RangeInterface
{
    use ToolsTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Volume::class);
    }

    public function getRange(int|string $journalIdentifier = null): array
    {
        $qb = $this->createQueryBuilder('v');
        $qb->distinct();
        $qb->select("v.vol_year AS year");

        if ($journalIdentifier) {
            $qb->andWhere('v.rvid = :rvId');
            $qb->setParameter('rvId', $journalIdentifier);
        }

        $qb->orderBy('year', 'DESC');

        return $qb->getQuery()->getResult();

    }


    public function getTypes(int|string $journalIdentifier = null): array
    {
        $distinctTypes = [];
        $qb = $this->createQueryBuilder('v');
        $qb->distinct();
        $qb->select("v.vol_type AS type");
        $qb->andWhere('v.vol_type IS NOT NULL');

        if ($journalIdentifier) {
            $qb->andWhere('v.rvid = :rvId');
            $qb->setParameter('rvId', $journalIdentifier);
        }
        $set = $qb->getQuery()->getResult();

        foreach ($set as $type) {
            $diff = $this->checkArrayEquality($distinctTypes, $type['type']);
            $outOfDistinctTypes = $diff['arrayDiff']['out'];
            if (!empty($outOfDistinctTypes)) {
                foreach ($outOfDistinctTypes as $value) {
                    $distinctTypes[] = $value;
                }
            }
        }

        return $distinctTypes;

    }

    public function getCommitteeQuery(int $rvId, int $vid): string
    {
        $sql = "SELECT uuid, CIV as `civ`, SCREEN_NAME AS `screenName`, ORCID AS `orcid` FROM ( SELECT UID, VID, `WHEN` FROM ( SELECT `ua`.* FROM `USER_ASSIGNMENT` AS `ua` INNER JOIN( SELECT `USER_ASSIGNMENT`.`ITEMID`, MAX(`WHEN`) AS `WHEN`, ITEMID as vid FROM `USER_ASSIGNMENT` WHERE (RVID = $rvId) AND (`USER_ASSIGNMENT`.`ITEMID` = $vid) AND(ITEM = 'volume') AND(ROLEID = 'editor') GROUP BY `ITEMID`, UID ) AS `r1` ON ua.ITEMID = r1.ITEMID AND ua.`WHEN` = r1.`WHEN` WHERE (RVID = $rvId) AND (ua.ITEMID = $vid) AND (ITEM = 'volume') AND(ROLEID = 'editor') AND( STATUS = 'active' ) ) AS `r2` INNER JOIN VOLUME AS v ON v.RVID = r2.RVID AND v.VID = r2.ITEMID) AS `result` INNER JOIN USER AS `u` ON result.UID = u.UID  GROUP BY VID,uuid ORDER BY u.SCREEN_NAME ASC;";
        return $sql;

    }


    public function getCommittee(int $rvId, int $vid): array
    {
        try {
            $result = $this->getEntityManager()
                ->getConnection()
                ->prepare($this->getCommitteeQuery($rvId, $vid))
                ->executeQuery()
                ->fetchAllAssociative();
        } catch (Exception $e) {
            trigger_error($e->getMessage());
            return [];
        }

        return $result;

    }


}
