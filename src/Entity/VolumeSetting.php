<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use App\AppConstants;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Table(name: self::TABLE)]
#[ORM\Index(columns: ['VID'], name: 'FK_RVID0_idx')]
#[ORM\Entity]
class VolumeSetting
{

    public const TABLE = 'VOLUME_SETTING';


    #[ORM\Column(name: 'VID', type: 'integer', nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy:'NONE')]
    private $vid;


    #[ORM\Column(name: 'SETTING', type: 'string', length: 200, nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'NONE')]

    #[Groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['collection']['read'][0],

        ]

    )]
    private $setting;


    #[ORM\Column(name: 'VALUE', type: 'text', length: 65535, nullable: true)]

    #[Groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['collection']['read'][0],

        ]

    )]
    private $value;

    #[ORM\ManyToOne(targetEntity: Volume::class, inversedBy: 'settings')]
    #[ORM\JoinColumn(name: 'VID', referencedColumnName: 'VID', nullable: false)]
    private ?Volume $volume = null;

    public function getVid(): ?int
    {
        return $this->vid;
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

    public function getVolume(): ?Volume
    {
        return $this->volume;
    }

    public function setVolume(?Volume $volume): self
    {
        $this->volume = $volume;

        return $this;
    }


}
