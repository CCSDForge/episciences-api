<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * News
 */
#[ORM\Entity]
#[ORM\Table(name: 'NEWS')]
#[ORM\Index(name: 'RVID', columns: ['RVID'])]
class LegacyNews
{
    /**
     * @var int
     */
    #[ORM\Column(name: 'NEWSID', type: 'integer', nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private $newsid;

    #[ORM\Column(name: 'RVID', type: 'integer', nullable: false, options: ['unsigned' => true])]
    private ?int $rvid = null;

    #[ORM\Column(name: 'UID', type: 'integer', nullable: false, options: ['unsigned' => true])]
    private ?int $uid = null;

    #[ORM\Column(name: 'LINK', type: 'string', length: 2000, nullable: false)]
    private ?string $link = null;

    #[ORM\Column(name: 'ONLINE', type: 'boolean', nullable: false)]
    private ?bool $online = null;

    #[ORM\Column(name: 'DATE_POST', type: 'datetime', nullable: false)]
    private \DateTime|\DateTimeInterface $datePost;
    public function __construct()
    {
        $this->datePost = new \DateTime('CURRENT_TIMESTAMP');
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
