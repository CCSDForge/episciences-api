<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * VolumePaperPosition
 *
 * @ORM\Table(name="VOLUME_PAPER_POSITION")
 * @ORM\Entity
 */
class VolumePaperPosition
{
    /**
     * @var int
     *
     * @ORM\Column(name="VID", type="integer", nullable=false, options={"unsigned"=true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $vid;

    /**
     * @var int
     *
     * @ORM\Column(name="PAPERID", type="integer", nullable=false, options={"unsigned"=true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $paperid;

    /**
     * @var int
     *
     * @ORM\Column(name="POSITION", type="integer", nullable=false, options={"unsigned"=true})
     */
    private $position;

    public function getVid(): ?int
    {
        return $this->vid;
    }

    public function getPaperid(): ?int
    {
        return $this->paperid;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }


}
