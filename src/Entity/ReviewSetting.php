<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: self::TABLE)]
#[ORM\Entity]


class ReviewSetting
{
    public const TABLE = 'REVIEW_SETTING';
    // todo to be implemented: new setting "allowBrowseAcceptedDocuments"
    public const ALLOW_BROWSE_ACCEPTED_ARTICLE = 'allowBrowseAcceptedDocuments';
    public const DISPLAY_EMPTY_VOLUMES = 'displayEmptyVolumes';
    /** Visibility of the statistics of the journal: hidden (absent, empty or "0", default), editorial secretaries ("2"), public (any other value, "1") */
    public const DISPLAY_STATISTICS = 'displayStatistics';
    public const DISPLAY_STATISTICS_PUBLIC = '1';
    public const DISPLAY_STATISTICS_SECRETARIES = '2';

    #[ORM\Column(name: 'RVID', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private int $rvid;

    #[ORM\Column(name: 'SETTING', type: \Doctrine\DBAL\Types\Types::STRING, length: 200, nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private string $setting;

    #[ORM\Column(name: 'VALUE', type: \Doctrine\DBAL\Types\Types::TEXT, length: 65535, nullable: true)]
    private ?string $value = null;

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
