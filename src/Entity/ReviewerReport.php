<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ReviewerReport
 *
 * @ORM\Table(name="REVIEWER_REPORT", uniqueConstraints={@ORM\UniqueConstraint(name="UID", columns={"UID", "DOCID"})}, indexes={@ORM\Index(name="ONBEHALF_UID", columns={"ONBEHALF_UID"})})
 * @ORM\Entity
 */
class ReviewerReport
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
     * @ORM\Column(name="UID", type="integer", nullable=false, options={"unsigned"=true})
     */
    private $uid;

    /**
     * @var int|null
     *
     * @ORM\Column(name="ONBEHALF_UID", type="integer", nullable=true, options={"unsigned"=true,"comment"="Mis à jour [!= de NULL] uniquement si l’évaluation est faite à la place de relecteur UID"})
     */
    private $onbehalfUid;

    /**
     * @var int
     *
     * @ORM\Column(name="DOCID", type="integer", nullable=false, options={"unsigned"=true})
     */
    private $docid;

    /**
     * @var int
     *
     * @ORM\Column(name="STATUS", type="integer", nullable=false, options={"unsigned"=true})
     */
    private $status;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="CREATION_DATE", type="datetime", nullable=false)
     */
    private $creationDate;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="UPDATE_DATE", type="datetime", nullable=true)
     */
    private $updateDate;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUid(): ?int
    {
        return $this->uid;
    }

    public function setUid(int $uid): self
    {
        $this->uid = $uid;

        return $this;
    }

    public function getOnbehalfUid(): ?int
    {
        return $this->onbehalfUid;
    }

    public function setOnbehalfUid(?int $onbehalfUid): self
    {
        $this->onbehalfUid = $onbehalfUid;

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

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function setStatus(int $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getCreationDate(): ?\DateTimeInterface
    {
        return $this->creationDate;
    }

    public function setCreationDate(\DateTimeInterface $creationDate): self
    {
        $this->creationDate = $creationDate;

        return $this;
    }

    public function getUpdateDate(): ?\DateTimeInterface
    {
        return $this->updateDate;
    }

    public function setUpdateDate(?\DateTimeInterface $updateDate): self
    {
        $this->updateDate = $updateDate;

        return $this;
    }


}
