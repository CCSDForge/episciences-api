<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * DoiQueue
 */
#[ORM\Entity]
#[ORM\Table(name: 'doi_queue')]
#[ORM\Index(name: 'doi_status', columns: ['doi_status'])]
#[ORM\UniqueConstraint(name: 'paperid', columns: ['paperid'])]
class DoiQueue
{
    /**
     * @var int
     */
    #[ORM\Column(name: 'id_doi_queue', type: 'integer', nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private $idDoiQueue;

    #[ORM\Column(name: 'paperid', type: 'integer', nullable: false, options: ['unsigned' => true])]
    private ?int $paperid = null;

    #[ORM\Column(name: 'doi_status', type: 'string', length: 0, nullable: false, options: ['default' => 'assigned'])]
    private string $doiStatus = 'assigned';

    #[ORM\Column(name: 'date_init', type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $dateInit = null;

    #[ORM\Column(name: 'date_updated', type: 'datetime', nullable: false)]
    private \DateTime|\DateTimeInterface $dateUpdated;
    public function __construct()
    {
        $this->dateUpdated = new \DateTime('CURRENT_TIMESTAMP');
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
