<?php

namespace App\Entity\Main;

use Doctrine\ORM\Mapping as ORM;

/**
 * VolumeSetting
 *
 * @ORM\Table(name="VOLUME_SETTING", indexes={@ORM\Index(name="FK_RVID0_idx", columns={"VID"})})
 * @ORM\Entity
 */
class VolumeSetting
{
    /**
     * @var int
     *
     * @ORM\Column(name="VID", type="integer", nullable=false, options={"unsigned"=true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $vid;

    /**
     * @var string
     *
     * @ORM\Column(name="SETTING", type="string", length=200, nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $setting;

    /**
     * @var string|null
     *
     * @ORM\Column(name="VALUE", type="text", length=65535, nullable=true)
     */
    private $value;

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


}
