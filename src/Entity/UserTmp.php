<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * UserTmp
 */
#[ORM\Entity]
#[ORM\Table(name: 'USER_TMP')]
class UserTmp
{
    #[ORM\Column(name: 'ID', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private int $id;

    #[ORM\Column(name: 'EMAIL', type: \Doctrine\DBAL\Types\Types::STRING, length: 250, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(name: 'FIRSTNAME', type: \Doctrine\DBAL\Types\Types::STRING, length: 100, nullable: true)]
    private ?string $firstname = null;

    #[ORM\Column(name: 'LASTNAME', type: \Doctrine\DBAL\Types\Types::STRING, length: 100, nullable: true)]
    private ?string $lastname = null;

    #[ORM\Column(name: 'LANG', type: \Doctrine\DBAL\Types\Types::STRING, length: 3, nullable: true)]
    private ?string $lang = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(?string $firstname): self
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function setLastname(?string $lastname): self
    {
        $this->lastname = $lastname;

        return $this;
    }

    public function getLang(): ?string
    {
        return $this->lang;
    }

    public function setLang(?string $lang): self
    {
        $this->lang = $lang;

        return $this;
    }


}
