<?php

namespace App\Repository;

use App\Entity\Section;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Section>
 *
 * @method Section|null find($id, $lockMode = null, $lockVersion = null)
 * @method Section|null findOneBy(array $criteria, array $orderBy = null)
 * @method Section[]    findAll()
 * @method Section[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SectionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Section::class);
    }


    /**
     * @param int $rvId
     * @param int|array $uid
     * @return array
     * @throws \Doctrine\DBAL\Exception
     * @throws \JsonException
     */
    public function getAssignedSection(int $rvId, int|array $uid = []): array
    {
        $sections = [];

        $result = $this->getEntityManager()
            ->getConnection()
            ->prepare($this->assignedSectionsQuery($rvId, $uid))
            ->executeQuery()
            ->fetchAllAssociative();

        foreach ($result as $values) {

            if (!isset($values['SID'])) {
                continue;
            }

            $section = new Section();
            $titles = $values['titles'] ? json_decode($values['titles'], true, 512, JSON_THROW_ON_ERROR) : null;
            $descriptions = $values['descriptions'] ? json_decode($values['descriptions'], true, 512, JSON_THROW_ON_ERROR) : null;
            $sections[$values['UID']][] = $section
                ->setSid($values['SID'])
                ->setRvid($values['RVID'])
                ->setPosition($values['POSITION'])
                ->setTitles($titles)
                ->setDescriptions($descriptions);
        }
        return $sections;
    }

    public function assignedSectionsQuery(int $rvId, int|array $uid = null): string
    {

        if (!is_array($uid)) {
            $uid = (array)$uid;
        }

        $implodedUids = implode(',', $uid);

        return "
        SELECT DISTINCT(ua.UID), ua.RVID, sc.SID,sc.titles, sc.descriptions, sc.POSITION
        FROM USER_ASSIGNMENT AS ua
        JOIN(
        SELECT ITEMID, MAX(`WHEN`) AS max_when
        FROM
        USER_ASSIGNMENT
        WHERE RVID = {$rvId} AND ITEM = 'section' AND ROLEID = 'editor' AND UID IN({$implodedUids})
        GROUP BY ITEMID, UID) AS r1
        ON ua.ITEMID = r1.ITEMID AND ua.`WHEN` = r1.max_when
        LEFT JOIN SECTION AS sc
        ON
        sc.RVID = ua.RVID AND sc.SID = ua.ITEMID
        WHERE ua.RVID = {$rvId} AND ua.ITEM = 'section' AND ua.ROLEID = 'editor' AND ua.STATUS = 'active' AND ua.UID IN({$implodedUids})
        ORDER BY sc.POSITION ASC;
        ";
    }


    public function getCommitteeQuery(int $rvId, int $sid): string
    {
        return "SELECT uuid, CIV as `civ`, SCREEN_NAME AS `screenName`, ORCID AS `orcid` FROM ( SELECT UID, SID, `WHEN` FROM ( SELECT `ua`.* FROM `USER_ASSIGNMENT` AS `ua` INNER JOIN( SELECT `USER_ASSIGNMENT`.`ITEMID`, MAX(`WHEN`) AS `WHEN`, ITEMID as sid FROM `USER_ASSIGNMENT` WHERE (RVID = $rvId) AND (`USER_ASSIGNMENT`.`ITEMID` = $sid) AND(ITEM = 'section') AND(ROLEID = 'editor') GROUP BY `ITEMID`, UID ) AS `r1` ON ua.ITEMID = r1.ITEMID AND ua.`WHEN` = r1.`WHEN` WHERE (RVID = $rvId) AND (ua.ITEMID = $sid) AND (ITEM = 'section') AND(ROLEID = 'editor') AND( STATUS = 'active' ) ) AS `r2` INNER JOIN SECTION AS s ON s.RVID = r2.RVID AND s.SID = r2.ITEMID) AS `result` INNER JOIN USER AS `u` ON result.UID = u.UID  GROUP BY SID, uuid, u.CIV, u.SCREEN_NAME, u.ORCID, u.LASTNAME ORDER BY u.LASTNAME ASC;";
    }


    public function getCommittee(int $rvId, int $sid): array
    {
        try {
            $result = $this->getEntityManager()
                ->getConnection()
                ->prepare($this->getCommitteeQuery($rvId, $sid))
                ->executeQuery()
                ->fetchAllAssociative();
        } catch (Exception $e) {
            trigger_error($e->getMessage());
            return [];
        }

        return $result;

    }
}
