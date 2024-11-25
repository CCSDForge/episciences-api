<?php

namespace App\Entity;

use App\Repository\ReviewerReportRepository;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * ReviewerReport
 */
#[ORM\Table(name: self::TABLE)]
#[ORM\Index(columns: ['ONBEHALF_UID'], name: 'ONBEHALF_UID')]
#[ORM\UniqueConstraint(name: 'UID', columns: ['UID', 'DOCID'])]
#[ORM\Entity(repositoryClass: ReviewerReportRepository::class)]
class ReviewerReport
{
    public const TABLE = 'REVIEWER_REPORT';  // rating has not started yet
    public const STATUS_PENDING = 0;  // rating is in progress
    public const STATUS_WIP = 1;
    public const STATUS_COMPLETED = 2; // rating is completed

    #[ORM\Column(name: 'ID', type: 'integer', nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private int $id;

    /**
     * @var int
     */
    #[ORM\Column(name: 'UID', type: 'integer', nullable: false, options: ['unsigned' => true])]
    private int $uid;

    /**
     * @var int|null
     */
    #[ORM\Column(name: 'ONBEHALF_UID', type: 'integer', nullable: true, options: ['unsigned' => true, 'comment' => 'Mis à jour [!= de NULL] uniquement si l’évaluation est faite à la place de relecteur UID'])]
    private ?int $onbehalfUid;

    /**
     * @var int
     */
    #[ORM\Column(name: 'DOCID', type: 'integer', nullable: false, options: ['unsigned' => true])]
    private int $docid;

    /**
     * @var int
     */
    #[ORM\Column(name: 'STATUS', type: 'integer', nullable: false, options: ['unsigned' => true])]
    private int $status;


    #[ORM\Column(name: 'CREATION_DATE', type: 'datetime', nullable: false)]
    private DateTimeInterface $creationDate;


    #[ORM\Column(name: 'UPDATE_DATE', type: 'datetime', nullable: true)]
    private ?DateTimeInterface $updateDate;

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
