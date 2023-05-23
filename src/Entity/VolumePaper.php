<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * VolumePaper
 *
 * @ORM\Table(name="VOLUME_PAPER", uniqueConstraints={@ORM\UniqueConstraint(name="UNIQUE", columns={"VID", "DOCID"})})
 * @ORM\Entity
 */
class VolumePaper
{
    /**
     * @var int
     *
     * @ORM\Column(name="ID", type="integer", nullable=false, options={"unsigned"=true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var int
     *
     * @ORM\Column(name="VID", type="integer", nullable=false, options={"unsigned"=true})
     */
    private $vid;

    /**
     * @var int
     *
     * @ORM\Column(name="DOCID", type="integer", nullable=false, options={"unsigned"=true})
     */
    private $docid;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVid(): ?int
    {
        return $this->vid;
    }

    public function setVid(int $vid): self
    {
        $this->vid = $vid;

        return $this;
    }

    public function getDocid(): ?int
    {
        return $this->docid;
    }

    public function setDocid(int $docid): self
    {
        $this->docid = $docid;

        return $this;
    }


}
