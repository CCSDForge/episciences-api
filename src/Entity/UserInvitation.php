<?php

namespace App\Entity;

use App\Repository\UserInvitationRepository;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Table(name: self::TABLE)]
#[ORM\Index(columns: ['TOKEN'], name: 'TOKEN')]
#[ORM\Index(columns: ['STATUS'], name: 'TOKEN')]
#[ORM\Index(columns: ['SENDER_UID'], name: 'SENDER_UID')]
#[ORM\Entity(repositoryClass: UserInvitationRepository::class)]


class UserInvitation
{
    public const TABLE = 'USER_INVITATION';

    /**
     * The only statuses Episciences writes (Episciences_User_Invitation; checked on 2026-10-29): an invitation is created
     * pending (default of the column), then accepted, declined or cancelled. The API never writes this table.
     * The column is a free VARCHAR: an older value, if any, would be returned as is.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_CANCELLED = 'cancelled';
    /**
     * Never stored: computed for a pending invitation past its EXPIRATION_DATE, as Episciences_User_Invitation::hasExpired()
     */
    public const STATUS_EXPIRED = 'expired';

    #[ORM\Column(name: 'ID', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private ?int $id = null;

    #[ORM\Column(
        name: 'AID', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true, 'comment' => 'Assignment ID']
    )]
    private int $aid;

    #[ORM\Column(name: 'STATUS', type: \Doctrine\DBAL\Types\Types::STRING, length: 50, nullable: false, options: ['default' => self::STATUS_PENDING])]
    private string $status = self::STATUS_PENDING;


    #[ORM\Column(name: 'TOKEN', type: \Doctrine\DBAL\Types\Types::STRING, length: 40, nullable: true)]
    private ?string $token = null;


    #[ORM\Column(name: 'SENDER_UID', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: true, options: ['unsigned' => true])]
    private ?int $senderUid = null;


    #[ORM\Column(name: 'SENDING_DATE', type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE, nullable: false)]
    private \DateTimeInterface $sendingDate;


    #[ORM\Column(name: 'EXPIRATION_DATE', type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE, nullable: false)]
    private \DateTimeInterface $expirationDate;

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
