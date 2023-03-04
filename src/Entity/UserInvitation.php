<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * UserInvitation
 *
 * @ORM\Table(name="USER_INVITATION", indexes={@ORM\Index(name="TOKEN", columns={"TOKEN"})})
 * @ORM\Entity
 */
class UserInvitation
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
     * @ORM\Column(name="AID", type="integer", nullable=false, options={"unsigned"=true,"comment"="Assignment ID"})
     */
    private $aid;

    /**
     * @var string
     *
     * @ORM\Column(name="STATUS", type="string", length=50, nullable=false, options={"default"="pending"})
     */
    private $status = 'pending';

    /**
     * @var string|null
     *
     * @ORM\Column(name="TOKEN", type="string", length=40, nullable=true)
     */
    private $token;

    /**
     * @var int|null
     *
     * @ORM\Column(name="SENDER_UID", type="integer", nullable=true, options={"unsigned"=true})
     */
    private $senderUid;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="SENDING_DATE", type="datetime", nullable=false)
     */
    private $sendingDate;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="EXPIRATION_DATE", type="datetime", nullable=false)
     */
    private $expirationDate;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAid(): ?int
    {
        return $this->aid;
    }

    public function setAid(int $aid): self
    {
        $this->aid = $aid;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(?string $token): self
    {
        $this->token = $token;

        return $this;
    }

    public function getSenderUid(): ?int
    {
        return $this->senderUid;
    }

    public function setSenderUid(?int $senderUid): self
    {
        $this->senderUid = $senderUid;

        return $this;
    }

    public function getSendingDate(): ?\DateTimeInterface
    {
        return $this->sendingDate;
    }

    public function setSendingDate(\DateTimeInterface $sendingDate): self
    {
        $this->sendingDate = $sendingDate;

        return $this;
    }

    public function getExpirationDate(): ?\DateTimeInterface
    {
        return $this->expirationDate;
    }

    public function setExpirationDate(\DateTimeInterface $expirationDate): self
    {
        $this->expirationDate = $expirationDate;

        return $this;
    }


}
