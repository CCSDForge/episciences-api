<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * DoiQueue
 */
#[ORM\Entity]
#[ORM\Table(name: 'doi_queue')]
#[ORM\Index(columns: ['doi_status'], name: 'doi_status')]
#[ORM\UniqueConstraint(name: 'paperid', columns: ['paperid'])]
class DoiQueue
{
    #[ORM\Column(name: 'id_doi_queue', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private int $idDoiQueue;

    #[ORM\Column(name: 'paperid', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    private int $paperid;

    #[ORM\Column(name: 'doi_status', type: \Doctrine\DBAL\Types\Types::STRING, length: 0, nullable: false, options: ['default' => 'assigned'])]
    private string $doiStatus = 'assigned';

    /**
     * @var \DateTime
     */
    #[ORM\Column(name: 'date_init', type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE, nullable: false)]
    private \DateTimeInterface $dateInit;

    /**
     * @var \DateTime
     */
    #[ORM\Column(name: 'date_updated', type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE, nullable: false)]
    private \DateTimeInterface $dateUpdated;
    public function __construct()
    {
        $this->dateUpdated = new \DateTime();
    }

    public function getIdDoiQueue(): ?int
    {
        return $this->idDoiQueue;
    }

    public function getPaperid(): ?int
    {
        return $this->paperid;
    }

    public function setPaperid(int $paperid): self
    {
        $this->paperid = $paperid;

        return $this;
    }

    public function getDoiStatus(): ?string
    {
        return $this->doiStatus;
    }

    public function setDoiStatus(string $doiStatus): self
    {
        $this->doiStatus = $doiStatus;

        return $this;
    }

    public function getDateInit(): ?\DateTimeInterface
    {
        return $this->dateInit;
    }

    public function setDateInit(\DateTimeInterface $dateInit): self
    {
        $this->dateInit = $dateInit;

        return $this;
    }

    public function getDateUpdated(): ?\DateTimeInterface
    {
        return $this->dateUpdated;
    }

    public function setDateUpdated(\DateTimeInterface $dateUpdated): self
    {
        $this->dateUpdated = $dateUpdated;

        return $this;
    }


}
