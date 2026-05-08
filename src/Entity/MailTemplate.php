<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * MailTemplate
 */
#[ORM\Entity]
#[ORM\Table(name: 'MAIL_TEMPLATE')]
#[ORM\Index(name: 'KEY', columns: ['KEY'])]
#[ORM\Index(name: 'RVCODE', columns: ['RVCODE'])]
#[ORM\Index(name: 'RVID', columns: ['RVID'])]
class MailTemplate
{
    /**
     * @var int
     */
    #[ORM\Column(name: 'ID', type: 'integer', nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private $id;

    #[ORM\Column(name: 'PARENTID', type: 'integer', nullable: true, options: ['unsigned' => true])]
    private ?int $parentid = null;

    #[ORM\Column(name: 'RVID', type: 'integer', nullable: true, options: ['unsigned' => true])]
    private ?int $rvid = null;

    #[ORM\Column(name: 'RVCODE', type: 'string', length: 25, nullable: true)]
    private ?string $rvcode = null;

    #[ORM\Column(name: 'KEY', type: 'string', length: 255, nullable: false)]
    private ?string $key = null;

    #[ORM\Column(name: 'TYPE', type: 'string', length: 255, nullable: false)]
    private ?string $type = null;

    #[ORM\Column(name: 'POSITION', type: 'integer', nullable: true, options: ['unsigned' => true])]
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
