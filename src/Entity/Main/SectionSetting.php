<?php

namespace App\Entity\Main;

use Doctrine\ORM\Mapping as ORM;

/**
 * SectionSetting
 *
 * @ORM\Table(name="SECTION_SETTING")
 * @ORM\Entity
 */
class SectionSetting
{
    /**
     * @var int
     *
     * @ORM\Column(name="SID", type="integer", nullable=false, options={"unsigned"=true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $sid;

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

    public function getSid(): ?int
    {
        return $this->sid;
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
