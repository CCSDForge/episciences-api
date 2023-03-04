<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * PaperSettings
 *
 * @ORM\Table(name="PAPER_SETTINGS")
 * @ORM\Entity
 */
class PaperSettings
{
    /**
     * @var int
     *
     * @ORM\Column(name="PSID", type="integer", nullable=false, options={"unsigned"=true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $psid;

    /**
     * @var int
     *
     * @ORM\Column(name="DOCID", type="integer", nullable=false, options={"unsigned"=true})
     */
    private $docid;

    /**
     * @var string
     *
     * @ORM\Column(name="SETTING", type="string", length=100, nullable=false)
     */
    private $setting;

    /**
     * @var string|null
     *
     * @ORM\Column(name="VALUE", type="string", length=250, nullable=true)
     */
    private $value;

    public function getPsid(): ?int
    {
        return $this->psid;
    }

    public function getDocid(): ?int
    {
        return $this->docid;
    }

    public function setDocid(int $docid): self
    {
        $this->docid = $docid;

        return $this;
    }

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


}
