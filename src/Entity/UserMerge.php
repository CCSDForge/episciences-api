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
    /**
     * @var int
     */
    #[ORM\Column(name: 'MID', type: 'integer', nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private $mid;

    #[ORM\Column(name: 'TOKEN', type: 'string', length: 40, nullable: true)]
    private ?string $token = null;

    #[ORM\Column(name: 'MERGER_UID', type: 'integer', nullable: false, options: ['unsigned' => true, 'comment' => 'CASID du compte à fusionner'])]
    private ?int $mergerUid = null;

    #[ORM\Column(name: 'KEEPER_UID', type: 'integer', nullable: false, options: ['unsigned' => true, 'comment' => 'CASID du compte à conserver'])]
    private ?int $keeperUid = null;

    #[ORM\Column(name: 'DETAIL', type: 'text', length: 65535, nullable: true)]
    private ?string $detail = null;

    #[ORM\Column(name: 'DATE', type: 'datetime', nullable: false)]
    private \DateTime|\DateTimeInterface $date;
    public function __construct()
    {
        $this->date = new \DateTime('CURRENT_TIMESTAMP');
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
