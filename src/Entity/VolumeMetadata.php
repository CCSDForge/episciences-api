<?php

namespace App\Entity;

use App\AppConstants;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;


#[ORM\Table(name: self::TABLE)]
#[ORM\Index(columns: ['VID'], name: 'VID')]
#[ORM\Index(columns: ['POSITION'], name: 'POSITION')]
#[ORM\Entity]
class VolumeMetadata
{

    public const  TABLE = 'VOLUME_METADATA';

    #[ORM\Column(name: 'ID', type: 'integer', nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private int $id;

    #[ORM\Column(name: 'VID', type: 'integer', nullable: false, options: ['unsigned' => true])]
    private int $vid;


    #[ORM\Column(name: 'POSITION', type: 'integer', nullable: false, options: ['unsigned' => true])]
    #[Groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['item']['read'][0],
        ]

    )]
    private ?int $position;

    #[Groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['collection']['read'][0]
        ]

    )]
    #[ORM\Column(name: 'titles', type: 'json', nullable: false)]
    private array $titles;

    #[ORM\Column(name: 'CONTENT', type: 'json', nullable: false)]
    #[Groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['collection']['read'][0]
        ]

    )]
    private ?array $content;

    #[ORM\Column(name: 'FILE', type: 'string', length: 250, nullable: true)]
    #[Groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['collection']['read'][0]

        ]

    )]
    private ?string $file;

    #[ORM\ManyToOne(targetEntity: Volume::class, inversedBy: 'metadata')]
    #[ORM\JoinColumn(name: 'VID', referencedColumnName: 'VID', nullable: false)]
    private ?Volume $volume = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['item']['read'][0],
        ]

    )]
    private ?\DateTimeInterface $date_creation = null;
    #[Groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['item']['read'][0],
        ]

    )]

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $date_updated = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVid(): ?int
    {
        return $this->vid;
    }

    public function setVid(int $vid): self
    {
        $this->vid = $vid;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getContent(): array
    {
        return (array)$this->content;
    }

    public function setContent(array $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getFile(): ?string
    {
        return $this->file;
    }

    public function setFile(?string $file): self
    {
        $this->file = $file;

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

    /**
     * @return array
     */
    public function getTitles(): array
    {
        return $this->titles;
    }

    /**
     * @param array $titles
     * @return VolumeMetadata
     */
    public function setTitles(array $titles): self
    {
        $this->titles = $titles;
        return $this;
    }

    public function getDateCreation(): ?\DateTimeInterface
    {
        return $this->date_creation;
    }

    public function setDateCreation(?\DateTimeInterface $date_creation): static
    {
        $this->date_creation = $date_creation;

        return $this;
    }

    public function getDateUpdated(): ?\DateTimeInterface
    {
        return $this->date_updated;
    }

    public function setDateUpdated(\DateTimeInterface $date_updated): static
    {
        $this->date_updated = $date_updated;

        return $this;
    }


}
