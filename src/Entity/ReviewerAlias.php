<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ReviewerAlias
 */
#[ORM\Entity]
#[ORM\Table(name: 'REVIEWER_ALIAS')]
class ReviewerAlias
{
    #[ORM\Column(name: 'UID', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private int $uid;

    #[ORM\Column(name: 'DOCID', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private int $docid;

    #[ORM\Column(name: 'ALIAS', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private int $alias;

    public function getUid(): ?int
    {
        return $this->uid;
    }

    public function getDocid(): ?int
    {
        return $this->docid;
    }

    public function getAlias(): ?int
    {
        return $this->alias;
    }


}
