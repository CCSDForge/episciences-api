<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Reminders
 *
 * @ORM\Table(name="REMINDERS")
 * @ORM\Entity
 */
class Reminders
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
     * @var int|null
     *
     * @ORM\Column(name="RVID", type="integer", nullable=true, options={"unsigned"=true})
     */
    private $rvid;

    /**
     * @var bool|null
     *
     * @ORM\Column(name="TYPE", type="boolean", nullable=true)
     */
    private $type;

    /**
     * @var int|null
     *
     * @ORM\Column(name="DELAY", type="smallint", nullable=true, options={"unsigned"=true})
     */
    private $delay;

    /**
     * @var string
     *
     * @ORM\Column(name="RECIPIENT", type="string", length=25, nullable=false, options={"default"="reviewer"})
     */
    private $recipient = 'reviewer';

    /**
     * @var string|null
     *
     * @ORM\Column(name="REPETITION", type="string", length=20, nullable=true)
     */
    private $repetition;

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
