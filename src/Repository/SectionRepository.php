<?php

namespace App\Repository;

use App\Entity\Paper;
use App\Entity\Section;
use App\Entity\UserAssignment;
use App\Traits\CommitteeTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;

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
    use CommitteeTrait;

    public function __construct(ManagerRegistry $registry, private readonly LoggerInterface $logger)
    {
        parent::__construct($registry, Section::class);
    }


    /**
     * The sections whose latest editor assignment of the given editors is active, indexed by editor (UID),
     * ordered by position; their number of published papers is counted by the query (their papers are not loaded).
     * @param int $rvId
     * @param int|array<int> $uid
     * @return array<int, list<Section>>
     * @throws \JsonException
     */
    public function getAssignedSection(int $rvId, int|array $uid = []): array
    {
        $sections = [];

        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('assignment.uid AS UID', 'assignment.rvid AS RVID', 'section.sid AS SID', 'section.titles AS titles', 'section.descriptions AS descriptions', 'section.position AS POSITION')
            ->addSelect(sprintf('(SELECT COUNT(published.docid) FROM %s published WHERE published.rvid = section.rvid AND published.sid = section.sid AND published.status = :published) AS %s', Paper::class, PapersRepository::TOTAL_ARTICLE))
            ->distinct()
            ->from(UserAssignment::class, 'assignment')
            // a removed section is not returned
            ->leftJoin(Section::class, 'section', Join::WITH, 'section.rvid = assignment.rvid AND section.sid = assignment.itemid')
            ->andWhere('assignment.uid IN (:uids)')
            ->setParameter('uids', array_map('intval', (array)$uid), ArrayParameterType::INTEGER)
            ->setParameter('published', Paper::STATUS_PUBLISHED)
            ->orderBy('section.position', 'ASC');

        $result = self::whereLatestActiveEditorAssignment($qb, 'assignment', $rvId, 'section')
            ->getQuery()
            ->getScalarResult();

        foreach ($result as $values) {

            if (!isset($values['SID'])) {
                continue;
            }

            $section = new Section();
            $section
                ->setSid((int)$values['SID'])
                ->setRvid((int)$values['RVID'])
                ->setPosition((int)$values['POSITION'])
                ->setTitles(self::jsonValue($values['titles']))
                ->setDescriptions(self::jsonValue($values['descriptions']));
            // the papers of the section are not loaded: their number is counted by the query
            $section->setTotalPublishedArticles((int) $values[PapersRepository::TOTAL_ARTICLE]);
            $sections[(int)$values['UID']][] = $section;
        }
        return $sections;
    }

    /**
     * The editors of the section, ordered by last name (public: no account identifier)
     * @return list<array{civ: ?string, screenName: string, orcid: ?string}>
     */
    public function getCommittee(int $rvId, int $sid): array
    {
        try {
            return $this->committee($rvId, $sid, 'section', Section::class, 'sid');
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
            return [];
        }
    }

    /**
     * A JSON column read by a scalar query: already decoded, or not
     * @return array<mixed>|null
     * @throws \JsonException
     */
    private static function jsonValue(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        return $value ? json_decode((string)$value, true, 512, JSON_THROW_ON_ERROR) : null;
    }
}
