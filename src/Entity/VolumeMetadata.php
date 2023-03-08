<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;


#[ORM\Table(name: 'VOLUME_METADATA')]
#[ORM\Index(columns: ['VID'], name: 'VID')]
class VolumeMetadata
{

    #[ORM\Column(name: 'ID', type: 'integer', nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private $id;

    #[ORM\Column(name: 'VID', type: 'integer', nullable: false, options: ['unsigned' => true])]
    private $vid;


    #[ORM\Column(name: 'POSITION', type: 'integer', nullable: false, options: ['unsigned' => true])]
    private $position;

    #[ORM\Column(name: 'CONTENT', type: 'boolean', nullable: false)]
    private $content;

    #[ORM\Column(name: 'FILE', type: 'string', length: 250, nullable: true)]
    private $file;

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

    public function getContent(): ?bool
    {
        return $this->content;
    }

    public function setContent(bool $content): self
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


}
