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
    /** DOI settings of the journal: on a page of their own in Episciences, for the platform administrators only */
    public const DOI_SETTINGS = ['doiPrefix', 'doiFormat', 'doiRegistrationAgency', 'doiAssignMode'];
    /** ISSN of the printed edition of the journal */
    public const SETTING_ISSN_PRINT = 'ISSN_PRINT';
    /** ISSN of the electronic edition of the journal */
    public const SETTING_ISSN = 'ISSN';
    /** Repositories to which the journal's accepted papers are automatically submitted (list of repository ids) */
    public const SETTING_REPOSITORIES = 'repositories';
    /** Whether automatic submission of accepted papers to external repositories is disabled for the journal */
    public const SETTING_DISABLE_AUTOMATIC_TRANSFER = 'disableAutomaticTransfer';

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
