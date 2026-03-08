<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * UserMerge
 */
#[ORM\Entity]
#[ORM\Table(name: 'USER_MERGE')]
class UserMerge
{
    #[ORM\Column(name: 'MID', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private int $mid;

    #[ORM\Column(name: 'TOKEN', type: \Doctrine\DBAL\Types\Types::STRING, length: 40, nullable: true)]
    private ?string $token = null;

    #[ORM\Column(name: 'MERGER_UID', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true, 'comment' => 'CASID du compte à fusionner'])]
    private int $mergerUid;

    #[ORM\Column(name: 'KEEPER_UID', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true, 'comment' => 'CASID du compte à conserver'])]
    private int $keeperUid;

    #[ORM\Column(name: 'DETAIL', type: \Doctrine\DBAL\Types\Types::TEXT, length: 65535, nullable: true)]
    private ?string $detail = null;

    /**
     * @var \DateTime
     */
    #[ORM\Column(name: 'DATE', type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE, nullable: false)]
    private \DateTimeInterface $date;
    public function __construct()
    {
        $this->date = new \DateTime();
    }

    public function getMid(): ?int
    {
        return $this->mid;
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

    public function getMergerUid(): ?int
    {
        return $this->mergerUid;
    }

    public function setMergerUid(int $mergerUid): self
    {
        $this->mergerUid = $mergerUid;

        return $this;
    }

    public function getKeeperUid(): ?int
    {
        return $this->keeperUid;
    }

    public function setKeeperUid(int $keeperUid): self
    {
        $this->keeperUid = $keeperUid;

        return $this;
    }

    public function getDetail(): ?string
    {
        return $this->detail;
    }

    public function setDetail(?string $detail): self
    {
        $this->detail = $detail;

        return $this;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }


}
