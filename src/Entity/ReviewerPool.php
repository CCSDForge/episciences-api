<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ReviewerPool
 *
 * @ORM\Table(name="REVIEWER_POOL")
 * @ORM\Entity
 */
class ReviewerPool
{
    /**
     * @var int
     *
     * @ORM\Column(name="RVID", type="integer", nullable=false, options={"unsigned"=true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $rvid;

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
     * @ORM\Column(name="UID", type="integer", nullable=false, options={"unsigned"=true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $uid;

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
