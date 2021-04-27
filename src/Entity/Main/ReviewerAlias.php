<?php

namespace App\Entity\Main;

use Doctrine\ORM\Mapping as ORM;

/**
 * ReviewerAlias
 *
 * @ORM\Table(name="REVIEWER_ALIAS")
 * @ORM\Entity
 */
class ReviewerAlias
{
    /**
     * @var int
     *
     * @ORM\Column(name="UID", type="integer", nullable=false, options={"unsigned"=true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $uid;

    /**
     * @var int
     *
     * @ORM\Column(name="DOCID", type="integer", nullable=false, options={"unsigned"=true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $docid;

    /**
     * @var int
     *
     * @ORM\Column(name="ALIAS", type="integer", nullable=false, options={"unsigned"=true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $alias;

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
