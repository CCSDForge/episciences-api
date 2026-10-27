<?php

declare(strict_types=1);

namespace App\Traits;

use App\Entity\User;
use App\Entity\UserAssignment;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;

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
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('editor.civ AS civ', 'editor.screenName AS screenName', 'editor.orcid AS orcid')
            ->from(UserAssignment::class, 'assignment')
            // the item exists
            ->innerJoin($itemClass, 'item', Join::WITH, sprintf('item.rvid = assignment.rvid AND item.%s = assignment.itemid', $itemIdField))
            ->innerJoin(User::class, 'editor', Join::WITH, 'editor.uid = assignment.uid')
            ->andWhere('assignment.itemid = :itemId')
            ->setParameter('itemId', $itemId)
            ->groupBy('editor.uid', 'editor.civ', 'editor.screenName', 'editor.orcid', 'editor.lastname')
            ->orderBy('editor.lastname', 'ASC');

        return self::whereLatestActiveEditorAssignment($qb, 'assignment', $rvId, $item)
            ->getQuery()
            ->getScalarResult();
    }

    /**
     * Keeps the active editor assignments to the items of the journal which are the latest assignment
     * of their editor to their item (see latestActiveEditorAssignment())
     *
     * @param string $alias alias of the assignment (UserAssignment)
     * @param string $item 'section' or 'volume'
     */
    private static function whereLatestActiveEditorAssignment(QueryBuilder $qb, string $alias, int $rvId, string $item): QueryBuilder
    {
        return $qb
            ->andWhere(self::latestActiveEditorAssignment($alias))
            ->setParameter('rvid', $rvId)
            ->setParameter('item', $item)
            ->setParameter('editor', 'editor')
            ->setParameter('active', 'active');
    }

    /**
     * DQL condition: an active editor assignment (parameters :rvid, :item, :editor and :active) which is the latest
     * assignment of its editor to its item: an editor whose latest assignment is inactive is left out,
     * even if an earlier active assignment has the date of the latest assignment of another editor.
     *
     * @param string $alias alias of the assignment (UserAssignment)
     */
    private static function latestActiveEditorAssignment(string $alias): string
    {
        $latest = sprintf(
            'SELECT MAX(latest.when) FROM %1$s latest WHERE latest.itemid = %2$s.itemid AND latest.uid = %2$s.uid AND latest.rvid = :rvid AND latest.item = :item AND latest.roleid = :editor',
            UserAssignment::class,
            $alias
        );

        return sprintf(
            '%1$s.rvid = :rvid AND %1$s.item = :item AND %1$s.roleid = :editor AND %1$s.status = :active AND %1$s.when = (%2$s)',
            $alias,
            $latest
        );
    }
}
