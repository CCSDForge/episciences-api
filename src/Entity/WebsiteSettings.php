<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * WebsiteSettings
 *
 * @ORM\Table(name="WEBSITE_SETTINGS")
 * @ORM\Entity
 */
class WebsiteSettings
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
     * @ORM\Column(name="SETTING", type="string", length=50, nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $setting;

    /**
     * @var string
     *
     * @ORM\Column(name="VALUE", type="string", length=1000, nullable=false)
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

    public function setValue(string $value): self
    {
        $this->value = $value;

        return $this;
    }


}
