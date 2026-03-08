<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Reminders
 */
#[ORM\Entity]
#[ORM\Table(name: 'REMINDERS')]
class Reminders
{
    #[ORM\Column(name: 'ID', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private int $id;

    #[ORM\Column(name: 'RVID', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: true, options: ['unsigned' => true])]
    private ?int $rvid = null;

    #[ORM\Column(name: 'TYPE', type: \Doctrine\DBAL\Types\Types::BOOLEAN, nullable: true)]
    private ?bool $type = null;

    #[ORM\Column(name: 'DELAY', type: \Doctrine\DBAL\Types\Types::SMALLINT, nullable: true, options: ['unsigned' => true])]
    private ?int $delay = null;

    #[ORM\Column(name: 'RECIPIENT', type: \Doctrine\DBAL\Types\Types::STRING, length: 25, nullable: false, options: ['default' => 'reviewer'])]
    private string $recipient = 'reviewer';

    #[ORM\Column(name: 'REPETITION', type: \Doctrine\DBAL\Types\Types::STRING, length: 20, nullable: true)]
    private ?string $repetition = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRvid(): ?int
    {
        return $this->rvid;
    }

    public function setRvid(?int $rvid): self
    {
        $this->rvid = $rvid;

        return $this;
    }

    public function getType(): ?bool
    {
        return $this->type;
    }

    public function setType(?bool $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getDelay(): ?int
    {
        return $this->delay;
    }

    public function setDelay(?int $delay): self
    {
        $this->delay = $delay;

        return $this;
    }

    public function getRecipient(): ?string
    {
        return $this->recipient;
    }

    public function setRecipient(string $recipient): self
    {
        $this->recipient = $recipient;

        return $this;
    }

    public function getRepetition(): ?string
    {
        return $this->repetition;
    }

    public function setRepetition(?string $repetition): self
    {
        $this->repetition = $repetition;

        return $this;
    }


}
