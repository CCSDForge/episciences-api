<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * MailLog
 *
 * @ORM\Table(name="MAIL_LOG")
 * @ORM\Entity
 */
class MailLog
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
     * @var int
     *
     * @ORM\Column(name="RVID", type="integer", nullable=false, options={"unsigned"=true})
     */
    private $rvid;

    /**
     * @var int|null
     *
     * @ORM\Column(name="DOCID", type="integer", nullable=true, options={"unsigned"=true})
     */
    private $docid;

    /**
     * @var string|null
     *
     * @ORM\Column(name="FROM", type="string", length=250, nullable=true)
     */
    private $from;

    /**
     * @var string|null
     *
     * @ORM\Column(name="REPLYTO", type="string", length=250, nullable=true)
     */
    private $replyto;

    /**
     * @var string
     *
     * @ORM\Column(name="TO", type="text", length=16777215, nullable=false)
     */
    private $to;

    /**
     * @var string|null
     *
     * @ORM\Column(name="CC", type="text", length=65535, nullable=true)
     */
    private $cc;

    /**
     * @var string|null
     *
     * @ORM\Column(name="BCC", type="text", length=65535, nullable=true)
     */
    private $bcc;

    /**
     * @var string|null
     *
     * @ORM\Column(name="SUBJECT", type="string", length=250, nullable=true)
     */
    private $subject;

    /**
     * @var string|null
     *
     * @ORM\Column(name="CONTENT", type="text", length=16777215, nullable=true)
     */
    private $content;

    /**
     * @var string|null
     *
     * @ORM\Column(name="FILES", type="text", length=16777215, nullable=true)
     */
    private $files;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="WHEN", type="datetime", nullable=false)
     */
    private $when;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRvid(): ?int
    {
        return $this->rvid;
    }

    public function setRvid(int $rvid): self
    {
        $this->rvid = $rvid;

        return $this;
    }

    public function getDocid(): ?int
    {
        return $this->docid;
    }

    public function setDocid(?int $docid): self
    {
        $this->docid = $docid;

        return $this;
    }

    public function getFrom(): ?string
    {
        return $this->from;
    }

    public function setFrom(?string $from): self
    {
        $this->from = $from;

        return $this;
    }

    public function getReplyto(): ?string
    {
        return $this->replyto;
    }

    public function setReplyto(?string $replyto): self
    {
        $this->replyto = $replyto;

        return $this;
    }

    public function getTo(): ?string
    {
        return $this->to;
    }

    public function setTo(string $to): self
    {
        $this->to = $to;

        return $this;
    }

    public function getCc(): ?string
    {
        return $this->cc;
    }

    public function setCc(?string $cc): self
    {
        $this->cc = $cc;

        return $this;
    }

    public function getBcc(): ?string
    {
        return $this->bcc;
    }

    public function setBcc(?string $bcc): self
    {
        $this->bcc = $bcc;

        return $this;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(?string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getFiles(): ?string
    {
        return $this->files;
    }

    public function setFiles(?string $files): self
    {
        $this->files = $files;

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


}
