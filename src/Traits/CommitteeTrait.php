<?php

declare(strict_types=1);

namespace App\Traits;

use App\Entity\User;
use App\Entity\UserAssignment;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;

/**
 * The editors of the sections and of the volumes, from their assignments
 */
trait CommitteeTrait
{
    abstract protected function getEntityManager(): EntityManagerInterface;

    /**
     * The editors whose latest assignment to the item is active, ordered by last name.
     *
     * @param string $item 'section' or 'volume'
     * @param class-string $itemClass Section or Volume
     * @param string $itemIdField identifier of the item (sid or vid)
     * @return list<array{civ: ?string, screenName: string, orcid: ?string}>
     */
    private function committee(int $rvId, int $itemId, string $item, string $itemClass, string $itemIdField): array
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('editor.civ AS civ', 'editor.screenName AS screenName', 'editor.orcid AS orcid')
            ->from(UserAssignment::class, 'assignment')
            // the item exists
            ->innerJoin($itemClass, 'item', Join::WITH, sprintf('item.rvid = assignment.rvid AND item.%s = assignment.itemid', $itemIdField))
            ->innerJoin(User::class, 'editor', Join::WITH, 'editor.uid = assignment.uid')
            ->andWhere('assignment.itemid = :itemId')
            ->andWhere(self::latestActiveEditorAssignment('assignment'))
            ->setParameter('rvid', $rvId)
            ->setParameter('itemId', $itemId)
            ->setParameter('item', $item)
            ->setParameter('editor', 'editor')
            ->setParameter('active', 'active')
            ->groupBy('editor.uid', 'editor.civ', 'editor.screenName', 'editor.orcid', 'editor.lastname')
            ->orderBy('editor.lastname', 'ASC')
            ->getQuery()
            ->getScalarResult();
    }

    /**
     * DQL condition: an active editor assignment (parameters :rvid, :item, :editor and :active) which is one of the latest
     * assignments of its item, i.e. dated as the latest assignment of one of the editors of the item.
     * As before the conversion to Doctrine, the date is not compared by editor.
     *
     * @param string $alias alias of the assignment (UserAssignment)
     * @param string $latestCondition additional condition on the assignments of the item (alias "latest")
     */
    private static function latestActiveEditorAssignment(string $alias, string $latestCondition = ''): string
    {
        $latest = sprintf(
            'SELECT MAX(latest.when) FROM %s latest WHERE latest.itemid = %s.itemid AND latest.rvid = :rvid AND latest.item = :item AND latest.roleid = :editor%s GROUP BY latest.uid',
            UserAssignment::class,
            $alias,
            $latestCondition !== '' ? ' AND ' . $latestCondition : ''
        );

        return sprintf(
            '%1$s.rvid = :rvid AND %1$s.item = :item AND %1$s.roleid = :editor AND %1$s.status = :active AND %1$s.when IN (%2$s)',
            $alias,
            $latest
        );
    }
}
