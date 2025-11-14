<?php

namespace App\Repository;

use App\AppConstants;
use App\Entity\Paper;
use App\Entity\Volume;
use App\Entity\VolumePaperPosition;
use App\Traits\ToolsTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\NativeQuery;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;

/**
 * @extends ServiceEntityRepository<Volume>
 */
class VolumeRepository extends AbstractRepository implements RangeInterface
{
    use ToolsTrait;

    public function __construct(ManagerRegistry $registry, private readonly PapersRepository $papersRepository, private readonly LoggerInterface $logger)
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
        $sql = "SELECT uuid, CIV as `civ`, SCREEN_NAME AS `screenName`, ORCID AS `orcid`
                FROM ( SELECT UID, VID, `WHEN`
                       FROM ( SELECT `ua`.* FROM `USER_ASSIGNMENT` AS `ua`
                           INNER JOIN(
                           SELECT `USER_ASSIGNMENT`.`ITEMID`, MAX(`WHEN`) AS `WHEN`, ITEMID as vid
                           FROM `USER_ASSIGNMENT`
                           WHERE (RVID = $rvId) AND (`USER_ASSIGNMENT`.`ITEMID` = $vid) AND(ITEM = 'volume') AND(ROLEID = 'editor')
                           GROUP BY `ITEMID`, UID
                           ) AS `r1` ON ua.ITEMID = r1.ITEMID AND ua.`WHEN` = r1.`WHEN`
                                            WHERE (RVID = $rvId) AND (ua.ITEMID = $vid) AND (ITEM = 'volume') AND(ROLEID = 'editor') AND( STATUS = 'active' )
                                            ) AS `r2`
                           INNER JOIN VOLUME AS v ON v.RVID = r2.RVID AND v.VID = r2.ITEMID
                       ) AS `result`
                    INNER JOIN USER AS `u` ON result.UID = u.UID
                GROUP BY VID,uuid, CIV, SCREEN_NAME, ORCID, u.LASTNAME
                ORDER BY u.LASTNAME ASC;";
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
            $this->logger->critical($e->getMessage());
            return [];
        }

        return $result;

    }

    public function fetchSortedPapers(int $vid = null): array
    {
        $result = $this->fetchSortedPapersQuery($vid)->getResult();

        $onlyPublishedCollection = new ArrayCollection();
        $privatePapersCollection = new ArrayCollection();

        $toBeProcessed = [];

        foreach ($result as $values) {

            if (!isset($toBeProcessed[$values['PAPERID']])) {

                // To be checked : Inconsistency in the database: duplicate position
                $toBeProcessed[$values['PAPERID']] = $values;

                $paper = (new Paper())
                    ->setDocid($values['DOCID'])
                    ->setPaperid($values['PAPERID'])
                    ->setVid($vid)
                    ->setStatus($values['STATUS']);


                $volumePaperPosition = (new VolumePaperPosition())
                    ->setVid($vid)
                    ->setPaperid($paper?->getPaperid())
                    ->setPosition($values['POSITION']);

                $paper->setVolumePaperPosition($volumePaperPosition);

                if ($paper->isPublished() && !$onlyPublishedCollection->contains($paper)) {
                    $onlyPublishedCollection->add($paper);

                } elseif (!$privatePapersCollection->contains($paper)) {
                    $privatePapersCollection->add($paper);
                }

                unset($paper, $volumePaperPosition);


            }

        }
        return ['privateCollection' => $privatePapersCollection, 'publicCollection' => $onlyPublishedCollection];
    }

    public function fetchSortedPapersQuery(int $vid = null): NativeQuery
    {
        // Création du mapping des colonnes du résultat
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('DOCID', 'DOCID');
        $rsm->addScalarResult('STATUS', 'STATUS');
        $rsm->addScalarResult('PAPERID', 'PAPERID');
        $rsm->addScalarResult('pv', 'pv');
        $rsm->addScalarResult('sv', 'sv');
        $rsm->addScalarResult('POSITION', 'POSITION');

        $sql =
            "SELECT
            t1.PAPERID,
            DOCID,
            t1.STATUS,
            pv,
            sv,
            vpp.POSITION
            FROM(
            SELECT
            p.DOCID,
            p.PAPERID,
            p.STATUS,
            p.VID AS pv,
            vp.VID AS sv
            FROM
            PAPERS p
            LEFT JOIN VOLUME_PAPER vp ON (p.DOCID = vp.DOCID)
            ) t1
            INNER JOIN VOLUME_PAPER_POSITION vpp
            ON (((vpp.VID = t1.pv OR vpp.VID = t1.sv) AND vpp.PAPERID = t1.PAPERID)) ";
        $sql .= "HAVING t1.STATUS != ";
        $sql .= Paper::STATUS_OBSOLETE;

        if ($vid !== null) {
            $sql .= " AND (t1.pv = $vid OR t1.sv = $vid)";
        }

        $sql .= " ORDER BY POSITION ASC";

        return $this->getEntityManager()->createNativeQuery($sql, $rsm);
    }

    public function listQuery(int $rvId = null, $isMaximumForced= true): QueryBuilder {

        $qb = $this->createQueryBuilder('v');

        if ($rvId) {
            $qb->andWhere('v.rvid = :rvId');
            $qb->setParameter('rvId', $rvId);
        } elseif($isMaximumForced) {
            $qb->setMaxResults(self::DEFAULT_MAX_RESULT);
        }

        return $qb;
    }

    public function listPaginator(int $page, int $itemPerPage, int $rvId = null): DoctrinePaginator
    {
        return $this->getPaginatedItems($this->listQuery($rvId), $page, $itemPerPage);
    }
}
