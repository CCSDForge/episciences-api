<?php

namespace App\Entity\Main;

use Doctrine\ORM\Mapping as ORM;

/**
 * MailTemplate
 *
 * @ORM\Table(name="MAIL_TEMPLATE", indexes={@ORM\Index(name="KEY", columns={"KEY"}), @ORM\Index(name="RVCODE", columns={"RVCODE"}), @ORM\Index(name="RVID", columns={"RVID"})})
 * @ORM\Entity
 */
class MailTemplate
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
     * @var int|null
     *
     * @ORM\Column(name="PARENTID", type="integer", nullable=true, options={"unsigned"=true})
     */
    private $parentid;

    /**
     * @var int|null
     *
     * @ORM\Column(name="RVID", type="integer", nullable=true, options={"unsigned"=true})
     */
    private $rvid;

    /**
     * @var string|null
     *
     * @ORM\Column(name="RVCODE", type="string", length=25, nullable=true)
     */
    private $rvcode;

    /**
     * @var string
     *
     * @ORM\Column(name="KEY", type="string", length=255, nullable=false)
     */
    private $key;

    /**
     * @var string
     *
     * @ORM\Column(name="TYPE", type="string", length=255, nullable=false)
     */
    private $type;

    /**
     * @var int|null
     *
     * @ORM\Column(name="POSITION", type="integer", nullable=true, options={"unsigned"=true})
     */
    private $position;

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
