<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ReviewSetting
 *
 * @ORM\Table(name="REVIEW_SETTING", indexes={@ORM\Index(name="FK_CONFIG_idx", columns={"RVID"})})
 * @ORM\Entity
 */
class ReviewSetting
{
    /**
     * @var int
     *
     * @ORM\Column(name="RVID", type="integer", nullable=false, options={"unsigned"=true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $rvid;

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


}
