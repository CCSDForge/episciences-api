<?php

namespace App\Repository;

use App\Entity\Paper;
use App\Entity\ReviewSetting;
use App\Entity\Volume;
use App\Entity\VolumePaperPosition;
use App\Traits\ToolsTrait;
use App\Traits\VolumeTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\NativeQuery;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;


class VolumeRepository extends AbstractRepository implements RangeInterface
{
    use ToolsTrait;
    use VolumeTrait;

    public function __construct(ManagerRegistry $registry, private readonly PapersRepository $papersRepository, private readonly VolumePaperRepository $volumePaperRepository, private readonly LoggerInterface $logger)
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
        $result = $this->arrayCleaner(array_column(array_values($qb->getQuery()->getResult()), 'year'));
        $this->processYearRanges($result);
        return $result;

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
            $outOfDistinctTypes = $diff['arrayDiff']['out'] ?? [];
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

    public function listQuery(array $filters = [], $isMaximumForced = false): QueryBuilder
    {
        $alias = 'v';

        $rvId = $filters['rvid'] ?? null;
        $types = $filters['type'] ?? [];
        $years = $filters['year'] ?? null;
        $vIds = $filters['vid'] ?? null;

        $isDisplayEmptyVolume = $filters[ReviewSetting::DISPLAY_EMPTY_VOLUMES] ?? false;
        $onlyPublished = !isset($context['filters']['isGranted']) || !$context['filters']['isGranted']; // FALSE IF GRANTED SECRETARY

        $qb = $this->createQueryBuilder($alias);

        if ($rvId) {
            $qb->andWhere("$alias.rvid = :rvId");
            $qb->setParameter('rvId', $rvId);
        }

        if (!$isDisplayEmptyVolume) {  //   all volumes with published papers
            $this->andOrExp($qb, sprintf('%s.vid', $alias ), $this->getNoEmptyVolumesIdentifiers($rvId, $onlyPublished));
        }

        if (!empty($types)){
            $this->andOrLikeExp($qb,sprintf('%s.vol_type', $alias), $types);
        }

        if ($years) {
            $this->andOrLikeExp($qb, sprintf('%s.vol_year', $alias), $years);
        }

        if ($vIds) {
            $this->andOrExp($qb, sprintf('%s.vid', $alias), $vIds);
        }

        if (
            !$rvId &&
            $isMaximumForced
        ) {
            $qb->setMaxResults(self::DEFAULT_MAX_RESULT); // To avoid possible OUT OF MEMORY errors
        }

        $qb->orderBy(sprintf('%s.position',$alias), 'ASC');

        return $qb;
    }

    public function listPaginator(int $page, int $itemPerPage, array $filtersContext = []): DoctrinePaginator
    {
        return $this->getPaginatedItems($this->listQuery($filtersContext), $page, $itemPerPage);
    }


    /**
     * @param int|null $rvId
     * @param bool $strictlyPublished
     * @param int|array|null $ids
     * @return QueryBuilder
     */
    public function getNoEmptyMasterVolumesQuery(int $rvId = null, bool $strictlyPublished = true, int|array $ids = null): QueryBuilder
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('p.vid')->from(Paper::class, 'p')->distinct();
        $qb->where('p.vid > 0');
        $this->addWhere($rvId, $qb, $strictlyPublished, $ids);
        return $qb;
    }

    /**
     * @param int|null $rvId
     * @param bool $onlyPublished
     * @param int|array|null $ids // for a specific volume's ids
     * @return array
     */

    private function getNoEmptyVolumesIdentifiers(int $rvId = null, bool $onlyPublished = true, int|array $ids = null): array
    {
        $noEmptyMasterIds = array_column(array_values($this->getNoEmptyMasterVolumesQuery($rvId, $onlyPublished, $ids)->getQuery()->getArrayResult()), 'vid');
        $noEmptySecondaryVolumesIds = array_column(array_values($this->volumePaperRepository->getNoEmptySecondaryVolumes($rvId, $onlyPublished, $ids)->getQuery()->getResult()), 'vid');
        return [...$noEmptyMasterIds, ...$noEmptySecondaryVolumesIds];

    }

    public function findOneByWithContext(array $criteria, ?array $orderBy = null, array $context = []): ?object
    {

        $vid = $criteria['vid'] ?? null;

        if (!$vid) {
            return null;
        }

        $isDisplayEmptyVolume = $context[ReviewSetting::DISPLAY_EMPTY_VOLUMES] ?? false;

        if ($isDisplayEmptyVolume) {
            return $this->findOneBy($criteria, $orderBy);
        }

        $onlyPublished = !isset($context['filters']['isGranted']) || !$context['filters']['isGranted']; // FALSE IF GRANTED SECRETARY
        $result = $this->getNoEmptyVolumesIdentifiers(null, $onlyPublished, $vid);

        if (in_array($vid, $result, true)) {
            return $this->findOneBy($criteria, $orderBy);
        }

        return null;
    }

    /**
     * Dans la BDD, le champ vol_year, précédemment saisi sous forme d'année,
     * est désormais saisi sous forme de chaîne de caractères (AAAA | AAAA-AAAA)
     * afin de permettre la saisie de l'année de début et de l'année de fin,
     * Ce qui a conduit à ce traitement particulier pour maintenir le fonctionnement actuel du filtre "year".
     * @param array $years
     * @return void
     */

    private function processYearRanges(array &$years = []): void
    {

        if (empty($years)) {
            return;
        }

        $tmpYears = [];

        foreach ($years as $currentYear) {

            if (!$this->isValideVolumeYear($currentYear)) {
                continue;
            }

            $separator = '-';

            if (str_contains($currentYear, $separator)) {

                $parts = explode($separator, $currentYear);

                $start = (int)$parts[0];
                $end = isset($parts[1]) ? (int)$parts[1] : 0;

                if ($end && $start > $end) {
                    [$start, $end] = [$end, $start];
                }

                $tmpYears = array_unique([...$tmpYears, ...[$start, $end]]);

            } else {
                $tmpYears = array_unique([...$tmpYears, ...[(int)$currentYear]]);
            }
        }

        rsort($tmpYears, SORT_NUMERIC);
        $years = $tmpYears;

    }


}
