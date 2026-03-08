<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * WebsiteSettings
 */
#[ORM\Entity]
#[ORM\Table(name: 'WEBSITE_SETTINGS')]
class WebsiteSettings
{
    #[ORM\Column(name: 'SID', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private int $sid;

    #[ORM\Column(name: 'SETTING', type: \Doctrine\DBAL\Types\Types::STRING, length: 50, nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private string $setting;

    #[ORM\Column(name: 'VALUE', type: \Doctrine\DBAL\Types\Types::STRING, length: 1000, nullable: false)]
    private string $value;

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
