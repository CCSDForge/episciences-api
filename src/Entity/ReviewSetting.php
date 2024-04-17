<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Table(name: self::TABLE)]
#[ORM\UniqueConstraint(name: 'RVID', columns: ['SETTING'])]
#[ORM\Entity(repositoryClass: ReviewSetting::class)]

class ReviewSetting
{
    public const TABLE = 'REVIEW_SETTING';

    #[ORM\Column(name: 'RVID', type: 'integer', nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private $rvid;

    #[ORM\Column(name: 'SETTING', type: 'string', length: 200, nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    #[Groups(
        [
            'read:Review',
            'read:Reviews'
        ]

    )]

    private $setting;
    #[Groups(
        [
            'read:Review',
            'read:Reviews'
        ]

    )]

    #[ORM\Column(name: 'VALUE', type: 'string', length: 65535, nullable: true)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'NONE')]




    private $value;

    #[ORM\ManyToOne(targetEntity: Review::class, inversedBy: 'settings')]
    #[ORM\JoinColumn(name: 'RVID', referencedColumnName: 'RVID', nullable: false)]

    private Review $review;

    public function getRvid(): ?int
    {
        return $this->rvid;
    }

    public function getSetting(): ?string
    {
        return $this->setting;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): self
    {
        $this->value = $value;

        return $this;
    }

    public function getReview(): Review
    {
        return $this->review;
    }

    public function setReview(Review $review): self
    {
        $this->review = $review;

        return $this;
    }


}
