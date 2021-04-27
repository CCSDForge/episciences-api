<?php

namespace App\Entity\Main;

use Doctrine\ORM\Mapping as ORM;

/**
 * PaperRatingGrid
 *
 * @ORM\Table(name="PAPER_RATING_GRID")
 * @ORM\Entity
 */
class PaperRatingGrid
{
    /**
     * @var int
     *
     * @ORM\Column(name="DOCID", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $docid;

    /**
     * @var int
     *
     * @ORM\Column(name="RGID", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $rgid;

    public function getDocid(): ?int
    {
        return $this->docid;
    }

    public function getRgid(): ?int
    {
        return $this->rgid;
    }


}
