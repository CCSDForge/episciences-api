<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * MailLog
 */
#[ORM\Entity]
#[ORM\Table(name: 'MAIL_LOG')]
class MailLog
{
    #[ORM\Column(name: 'ID', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private int $id;

    #[ORM\Column(name: 'RVID', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    private int $rvid;

    #[ORM\Column(name: 'DOCID', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: true, options: ['unsigned' => true])]
    private ?int $docid = null;

    #[ORM\Column(name: 'FROM', type: \Doctrine\DBAL\Types\Types::STRING, length: 250, nullable: true)]
    private ?string $from = null;

    #[ORM\Column(name: 'REPLYTO', type: \Doctrine\DBAL\Types\Types::STRING, length: 250, nullable: true)]
    private ?string $replyto = null;

    #[ORM\Column(name: 'TO', type: \Doctrine\DBAL\Types\Types::TEXT, length: 16777215, nullable: false)]
    private string $to;

    #[ORM\Column(name: 'CC', type: \Doctrine\DBAL\Types\Types::TEXT, length: 65535, nullable: true)]
    private ?string $cc = null;

    #[ORM\Column(name: 'BCC', type: \Doctrine\DBAL\Types\Types::TEXT, length: 65535, nullable: true)]
    private ?string $bcc = null;

    #[ORM\Column(name: 'SUBJECT', type: \Doctrine\DBAL\Types\Types::STRING, length: 250, nullable: true)]
    private ?string $subject = null;

    #[ORM\Column(name: 'CONTENT', type: \Doctrine\DBAL\Types\Types::TEXT, length: 16777215, nullable: true)]
    private ?string $content = null;

    #[ORM\Column(name: 'FILES', type: \Doctrine\DBAL\Types\Types::TEXT, length: 16777215, nullable: true)]
    private ?string $files = null;

    /**
     * @var \DateTime
     */
    #[ORM\Column(name: 'WHEN', type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE, nullable: false)]
    private \DateTimeInterface $when;

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
