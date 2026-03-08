<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * News
 */
#[ORM\Entity]
#[ORM\Table(name: 'NEWS')]
#[ORM\Index(columns: ['RVID'], name: 'RVID')]
class LegacyNews
{
    #[ORM\Column(name: 'NEWSID', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private int $newsid;

    #[ORM\Column(name: 'RVID', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    private int $rvid;

    #[ORM\Column(name: 'UID', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    private int $uid;

    #[ORM\Column(name: 'LINK', type: \Doctrine\DBAL\Types\Types::STRING, length: 2000, nullable: false)]
    private string $link;

    #[ORM\Column(name: 'ONLINE', type: \Doctrine\DBAL\Types\Types::BOOLEAN, nullable: false)]
    private bool $online;

    /**
     * @var \DateTime
     */
    #[ORM\Column(name: 'DATE_POST', type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE, nullable: false)]
    private \DateTimeInterface $datePost;
    public function __construct()
    {
        $this->datePost = new \DateTime();
    }

    public function getNewsid(): ?int
    {
        return $this->newsid;
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

    public function getUid(): ?int
    {
        return $this->uid;
    }

    public function setUid(int $uid): self
    {
        $this->uid = $uid;

        return $this;
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function setLink(string $link): self
    {
        $this->link = $link;

        return $this;
    }

    public function getOnline(): ?bool
    {
        return $this->online;
    }

    public function setOnline(bool $online): self
    {
        $this->online = $online;

        return $this;
    }

    public function getDatePost(): ?\DateTimeInterface
    {
        return $this->datePost;
    }

    public function setDatePost(\DateTimeInterface $datePost): self
    {
        $this->datePost = $datePost;

        return $this;
    }


}
