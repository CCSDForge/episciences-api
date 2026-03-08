<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * MailTemplate
 */
#[ORM\Entity]
#[ORM\Table(name: 'MAIL_TEMPLATE')]
#[ORM\Index(columns: ['KEY'], name: 'KEY')]
#[ORM\Index(columns: ['RVCODE'], name: 'RVCODE')]
#[ORM\Index(columns: ['RVID'], name: 'RVID')]
class MailTemplate
{
    #[ORM\Column(name: 'ID', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private int $id;

    #[ORM\Column(name: 'PARENTID', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: true, options: ['unsigned' => true])]
    private ?int $parentid = null;

    #[ORM\Column(name: 'RVID', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: true, options: ['unsigned' => true])]
    private ?int $rvid = null;

    #[ORM\Column(name: 'RVCODE', type: \Doctrine\DBAL\Types\Types::STRING, length: 25, nullable: true)]
    private ?string $rvcode = null;

    #[ORM\Column(name: 'KEY', type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: false)]
    private string $key;

    #[ORM\Column(name: 'TYPE', type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: false)]
    private string $type;

    #[ORM\Column(name: 'POSITION', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: true, options: ['unsigned' => true])]
    private ?int $position = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParentid(): ?int
    {
        return $this->parentid;
    }

    public function setParentid(?int $parentid): self
    {
        $this->parentid = $parentid;

        return $this;
    }

    public function getRvid(): ?int
    {
        return $this->rvid;
    }

    public function setRvid(?int $rvid): self
    {
        $this->rvid = $rvid;

        return $this;
    }

    public function getRvcode(): ?string
    {
        return $this->rvcode;
    }

    public function setRvcode(?string $rvcode): self
    {
        $this->rvcode = $rvcode;

        return $this;
    }

    public function getKey(): ?string
    {
        return $this->key;
    }

    public function setKey(string $key): self
    {
        $this->key = $key;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): self
    {
        $this->position = $position;

        return $this;
    }


}
