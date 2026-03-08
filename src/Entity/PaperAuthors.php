<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * PaperAuthors
 */
#[ORM\Entity]
#[ORM\Table(name: 'PAPER_AUTHORS')]
#[ORM\Index(columns: ['AUTHORID'], name: 'PAPER_AUTHOR')]
class PaperAuthors
{
    #[ORM\Column(name: 'ID', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private int $id;

    #[ORM\Column(name: 'DOCID', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    private int $docid;

    #[ORM\Column(name: 'UID', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    private int $uid = 0;

    #[ORM\Column(name: 'POSITION', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: true, options: ['unsigned' => true, 'comment' => 'Classement des auteurs'])]
    private ?int $position = null;

    /**
     * @var \Authors
     */
    #[ORM\ManyToOne(targetEntity: \Authors::class)]
    #[ORM\JoinColumn(name: 'AUTHORID', referencedColumnName: 'ID')]
    private $authorid;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getUid(): ?int
    {
        return $this->uid;
    }

    public function setUid(int $uid): self
    {
        $this->uid = $uid;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getAuthorid(): ?Authors
    {
        return $this->authorid;
    }

    public function setAuthorid(?Authors $authorid): self
    {
        $this->authorid = $authorid;

        return $this;
    }


}
