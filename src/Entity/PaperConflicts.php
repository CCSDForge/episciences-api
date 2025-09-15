<?php

namespace App\Entity;

use App\AppConstants;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\PaperConflictsRepository;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Table(name: self::TABLE)]
#[ORM\UniqueConstraint(name: 'U_PAPERID_BY', columns: ['paper_id', 'by' ])]
#[ORM\Index(columns: ['answer'], name: 'answer')]
#[ORM\Index(columns: ['by'], name: 'BY_UID' )]
#[ORM\Index(columns: ['paper_id'], name: 'PAPERID' )]
#[ORM\Entity(repositoryClass: PaperConflictsRepository::class)]
class PaperConflicts
{
    public const TABLE = 'paper_conflicts';
    public const AVAILABLE_ANSWER = [
        'yes' => 'yes',
        'no' => 'no',
        'later' => 'later'
    ];

    #[ORM\Column(name: 'cid', type: 'integer', nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private int $cid;

    #[ORM\Column(name: 'paper_id', type: 'integer', nullable: false, options: ['unsigned' => true])]
    #[groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['collection']['read'][0]
        ]
    )]
    private int $paperId;

    #[ORM\Column(name: 'by', type: 'integer', nullable: false, options: ['unsigned' => true])]
    #[groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['collection']['read'][0]
        ]
    )]
    private int $by;


    #[ORM\Column(name: 'answer', type: 'string', length: 0, options: ['unsigned' => true])]
    #[groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['collection']['read'][0]
        ]
    )]
    private string $answer;

    #[ORM\Column(name: 'message', type: 'text', length: 65535, nullable: true)]
    #[groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['collection']['read'][0]
        ]
    )]
    private $message;

    #[ORM\Column(name: 'date', type: 'datetime', nullable: false, options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['collection']['read'][0]
        ]
    )]
    private $date = 'CURRENT_TIMESTAMP';

    #[ORM\ManyToOne( fetch: 'EAGER', inversedBy: 'conflicts')]
    #[ORM\JoinColumn(name: 'paper_id', referencedColumnName: 'DOCID', nullable: true)]
    private ?Paper $papers = null;

    public function getCid(): ?int
    {
        return $this->cid;
    }

    public function getPaperId(): ?int
    {
        return $this->paperId;
    }

    public function setPaperId(int $paperId): self
    {
        $this->paperId = $paperId;

        return $this;
    }

    public function getBy(): ?int
    {
        return $this->by;
    }

    public function setBy(int $by): self
    {
        $this->by = $by;

        return $this;
    }

    public function getAnswer(): ?string
    {
        return $this->answer;
    }

    public function setAnswer(string $answer): self
    {
        $this->answer = $answer;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getPapers(): ?Paper
    {
        return $this->papers;
    }

    public function setPapers(?Paper $papers): self
    {
        $this->papers = $papers;

        return $this;
    }


}