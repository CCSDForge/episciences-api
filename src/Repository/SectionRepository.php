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
     * @throws \Doctrine\DBAL\Exception
     * @throws \JsonException
     */
    public function getAssignedSection(int $rvId, int|array $uid = []): array
    {
        $sections = [];

        if (!is_array($uid)) {
            $uid = (array)$uid;
        }

        $params = array_merge([$rvId], $uid, [$rvId], $uid);

        $result = $this->getEntityManager()
            ->getConnection()
            ->executeQuery($this->assignedSectionsQuery($uid), $params)
            ->fetchAllAssociative();

        foreach ($result as $values) {

            if (!isset($values['SID'])) {
                continue;
            }

            $section = new Section();
            $titles = $values['titles'] ? json_decode((string) $values['titles'], true, 512, JSON_THROW_ON_ERROR) : null;
            $descriptions = $values['descriptions'] ? json_decode((string) $values['descriptions'], true, 512, JSON_THROW_ON_ERROR) : null;
            $sections[$values['UID']][] = $section
                ->setSid($values['SID'])
                ->setRvid($values['RVID'])
                ->setPosition($values['POSITION'])
                ->setTitles($titles)
                ->setDescriptions($descriptions);
        }
        return $sections;
    }

    /**
     * Returns a parameterized SQL query for assigned sections.
     * Callers must supply [$rvId, ...$uid, $rvId, ...$uid] as bound parameters.
     */
    public function assignedSectionsQuery(int|array $uid = []): string
    {
        if (!is_array($uid)) {
            $uid = (array)$uid;
        }

        $placeholders = implode(',', array_fill(0, count($uid), '?'));

        return "
        SELECT DISTINCT(ua.UID), ua.RVID, sc.SID,sc.titles, sc.descriptions, sc.POSITION
        FROM USER_ASSIGNMENT AS ua
        JOIN(
        SELECT ITEMID, MAX(`WHEN`) AS max_when
        FROM
        USER_ASSIGNMENT
        WHERE RVID = ? AND ITEM = 'section' AND ROLEID = 'editor' AND UID IN($placeholders)
        GROUP BY ITEMID, UID) AS r1
        ON ua.ITEMID = r1.ITEMID AND ua.`WHEN` = r1.max_when
        LEFT JOIN SECTION AS sc
        ON
        sc.RVID = ua.RVID AND sc.SID = ua.ITEMID
        WHERE ua.RVID = ? AND ua.ITEM = 'section' AND ua.ROLEID = 'editor' AND ua.STATUS = 'active' AND ua.UID IN($placeholders)
        ORDER BY sc.POSITION ASC;
        ";
    }


    /**
     * Returns a parameterized SQL query for section committee members.
     * Callers must supply [$rvId, $sid, $rvId, $sid] as bound parameters.
     */
    public function getCommitteeQuery(): string
    {
        return "SELECT uuid, CIV as `civ`, SCREEN_NAME AS `screenName`, ORCID AS `orcid` FROM ( SELECT UID, SID, `WHEN` FROM ( SELECT `ua`.* FROM `USER_ASSIGNMENT` AS `ua` INNER JOIN( SELECT `USER_ASSIGNMENT`.`ITEMID`, MAX(`WHEN`) AS `WHEN`, ITEMID as sid FROM `USER_ASSIGNMENT` WHERE (RVID = ?) AND (`USER_ASSIGNMENT`.`ITEMID` = ?) AND(ITEM = 'section') AND(ROLEID = 'editor') GROUP BY `ITEMID`, UID ) AS `r1` ON ua.ITEMID = r1.ITEMID AND ua.`WHEN` = r1.`WHEN` WHERE (RVID = ?) AND (ua.ITEMID = ?) AND (ITEM = 'section') AND(ROLEID = 'editor') AND( STATUS = 'active' ) ) AS `r2` INNER JOIN SECTION AS s ON s.RVID = r2.RVID AND s.SID = r2.ITEMID) AS `result` INNER JOIN USER AS `u` ON result.UID = u.UID  GROUP BY SID, uuid, u.CIV, u.SCREEN_NAME, u.ORCID, u.LASTNAME ORDER BY u.LASTNAME ASC;";
    }


    /**
     * @throws Exception
     */
    public function getCommittee(int $rvId, int $sid): array
    {
        return $this->getEntityManager()
            ->getConnection()
            ->executeQuery($this->getCommitteeQuery(), [$rvId, $sid, $rvId, $sid])
            ->fetchAllAssociative();
    }
}
