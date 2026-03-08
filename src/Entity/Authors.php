<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Authors
 */
#[ORM\Entity]
#[ORM\Table(name: 'AUTHORS')]
class Authors
{
    #[ORM\Column(name: 'ID', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private int $id;

    #[ORM\Column(name: 'FIRSTNAME', type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: false)]
    private string $firstname;

    #[ORM\Column(name: 'LASTNAME', type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: false)]
    private string $lastname;

    #[ORM\Column(name: 'ORCID', type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: false)]
    private string $orcid;

    #[ORM\Column(name: 'UID', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    private int $uid;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(string $firstname): self
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function setLastname(string $lastname): self
    {
        $this->lastname = $lastname;

        return $this;
    }

    public function getOrcid(): ?string
    {
        return $this->orcid;
    }

    public function setOrcid(string $orcid): self
    {
        $this->orcid = $orcid;

        return $this;
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


}
