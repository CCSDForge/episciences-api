<?php

namespace App\Entity\Main;

use Doctrine\ORM\Mapping as ORM;

/**
 * PaperComments
 *
 * @ORM\Table(name="PAPER_COMMENTS", indexes={@ORM\Index(name="DOCID", columns={"DOCID"})})
 * @ORM\Entity
 */
class PaperComments
{
    /**
     * @var int
     *
     * @ORM\Column(name="PCID", type="integer", nullable=false, options={"unsigned"=true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $pcid;

    /**
     * @var int|null
     *
     * @ORM\Column(name="PARENTID", type="integer", nullable=true, options={"unsigned"=true})
     */
    private $parentid;

    /**
     * @var int
     *
     * @ORM\Column(name="TYPE", type="integer", nullable=false, options={"unsigned"=true})
     */
    private $type;

    /**
     * @var int
     *
     * @ORM\Column(name="DOCID", type="integer", nullable=false, options={"unsigned"=true})
     */
    private $docid;

    /**
     * @var int
     *
     * @ORM\Column(name="UID", type="integer", nullable=false, options={"unsigned"=true})
     */
    private $uid;

    /**
     * @var string|null
     *
     * @ORM\Column(name="MESSAGE", type="text", length=16777215, nullable=true)
     */
    private $message;

    /**
     * @var string|null
     *
     * @ORM\Column(name="FILE", type="string", length=200, nullable=true)
     */
    private $file;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="WHEN", type="datetime", nullable=false)
     */
    private $when;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="DEADLINE", type="date", nullable=true)
     */
    private $deadline;

    /**
     * @var string|null
     *
     * @ORM\Column(name="OPTIONS", type="text", length=65535, nullable=true)
     */
    private $options;

    public function getPcid(): ?int
    {
        return $this->pcid;
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

    public function getType(): ?int
    {
        return $this->type;
    }

    public function setType(int $type): self
    {
        $this->type = $type;

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

    public function getUid(): ?int
    {
        return $this->uid;
    }

    public function setUid(int $uid): self
    {
        $this->uid = $uid;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function getFile(): ?string
    {
        return $this->file;
    }

    public function setFile(?string $file): self
    {
        $this->file = $file;

        return $this;
    }

    public function getWhen(): ?\DateTimeInterface
    {
        return $this->when;
    }

    public function setWhen(\DateTimeInterface $when): self
    {
        $this->when = $when;

        return $this;
    }

    public function getDeadline(): ?\DateTimeInterface
    {
        return $this->deadline;
    }

    public function setDeadline(?\DateTimeInterface $deadline): self
    {
        $this->deadline = $deadline;

        return $this;
    }

    public function getOptions(): ?string
    {
        return $this->options;
    }

    public function setOptions(?string $options): self
    {
        $this->options = $options;

        return $this;
    }


}
