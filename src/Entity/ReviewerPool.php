<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ReviewerPool
 */
#[ORM\Entity]
#[ORM\Table(name: 'REVIEWER_POOL')]
class ReviewerPool
{
    /**
     * @var int
     */
    #[ORM\Column(name: 'RVID', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?int $rvid = null;

    /**
     * @var int
     */
    #[ORM\Column(name: 'VID', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?int $vid = null;

    /**
     * @var int
     */
    #[ORM\Column(name: 'UID', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?int $uid = null;

    public function getRvid(): ?int
    {
        return $this->rvid;
    }

    public function getVid(): ?int
    {
        return $this->vid;
    }

    public function getUid(): ?int
    {
        return $this->uid;
    }


}
