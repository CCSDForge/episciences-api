<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * DoiQueue
 *
 * @ORM\Table(name="doi_queue", uniqueConstraints={@ORM\UniqueConstraint(name="paperid", columns={"paperid"})}, indexes={@ORM\Index(name="doi_status", columns={"doi_status"})})
 * @ORM\Entity
 */
class DoiQueue
{
    /**
     * @var int
     *
     * @ORM\Column(name="id_doi_queue", type="integer", nullable=false, options={"unsigned"=true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $idDoiQueue;

    /**
     * @var int
     *
     * @ORM\Column(name="paperid", type="integer", nullable=false, options={"unsigned"=true})
     */
    private $paperid;

    /**
     * @var string
     *
     * @ORM\Column(name="doi_status", type="string", length=0, nullable=false, options={"default"="assigned"})
     */
    private $doiStatus = 'assigned';

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="date_init", type="datetime", nullable=false)
     */
    private $dateInit;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="date_updated", type="datetime", nullable=false, options={"default"="CURRENT_TIMESTAMP"})
     */
    private $dateUpdated = 'CURRENT_TIMESTAMP';

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
