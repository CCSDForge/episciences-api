<?php

namespace App\Entity;

use App\AppConstants;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * SectionSetting
 */
#[ORM\Table(name: self::TABLE)]
#[ORM\Index(columns: ['SID'], name: 'FK_SID0_idx')]
#[ORM\Entity]
class SectionSetting
{
    public const TABLE = 'SECTION_SETTING';
    #[ORM\Column(name: 'SID', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private int $sid;

    #[ORM\Column(name: 'SETTING', type: \Doctrine\DBAL\Types\Types::STRING, length: 200, nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    #[Groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['section']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['section']['collection']['read'][0],

        ]

    )]
    private string $setting;

    #[ORM\Column(name: 'VALUE', type: \Doctrine\DBAL\Types\Types::TEXT, length: 65535, nullable: true)]

    #[Groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['section']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['section']['collection']['read'][0],

        ]

    )]
    private ?string $value = null;

    #[ORM\ManyToOne(targetEntity: Section::class, inversedBy: 'settings')]
    #[ORM\JoinColumn(name: 'SID', referencedColumnName: 'SID', nullable: false)]
    private ?Section $section = null;

    public function getSetting(): ?string
    {
        return $this->setting;
    }

    public function setSetting(string $setting): self
    {
        $this->setting = $setting;
        return $this;
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

    public function getSid(): int
    {
        return $this->sid;
    }

    public function setSid(int $sid): void
    {
        $this->sid = $sid;
    }

    public function getSection(): ?Section
    {
        return $this->section;
    }

    public function setSection(?Section $section): void
    {
        $this->section = $section;
    }


}
