<?php

namespace App\Entity\Main;

use Doctrine\ORM\Mapping as ORM;

/**
 * VolumeMetadata
 *
 * @ORM\Table(name="VOLUME_METADATA", indexes={@ORM\Index(name="VID", columns={"VID"})})
 * @ORM\Entity
 */
class VolumeMetadata
{
    /**
     * @var int
     *
     * @ORM\Column(name="ID", type="integer", nullable=false, options={"unsigned"=true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var int
     *
     * @ORM\Column(name="VID", type="integer", nullable=false, options={"unsigned"=true})
     */
    private $vid;

    /**
     * @var int
     *
     * @ORM\Column(name="POSITION", type="integer", nullable=false, options={"unsigned"=true})
     */
    private $position;

    /**
     * @var bool
     *
     * @ORM\Column(name="CONTENT", type="boolean", nullable=false)
     */
    private $content;

    /**
     * @var string|null
     *
     * @ORM\Column(name="FILE", type="string", length=250, nullable=true)
     */
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
