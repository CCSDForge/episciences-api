<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * PaperRatingGrid
 */
#[ORM\Entity]
#[ORM\Table(name: 'PAPER_RATING_GRID')]
class PaperRatingGrid
{
    #[ORM\Column(name: 'DOCID', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private int $docid;

    #[ORM\Column(name: 'RGID', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private int $rgid;

    public function getDocid(): ?int
    {
        return $this->docid;
    }

    public function getRgid(): ?int
    {
        return $this->rgid;
    }


}
