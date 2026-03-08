<?php

namespace App\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * PaperDatasets
 */
#[ORM\Entity]
#[ORM\Table(name: 'paper_datasets')]
#[ORM\Index(columns: ['code'], name: 'code')]
#[ORM\Index(columns: ['doc_id'], name: 'doc_id')]
#[ORM\Index(columns: ['name'], name: 'name')]
#[ORM\Index(columns: ['source_id'], name: 'source_id')]
#[ORM\UniqueConstraint(name: 'unique', columns: ['doc_id', 'code', 'name', 'value', 'source_id'])]
class PaperDatasets
{
    #[ORM\Column(name: 'id', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private readonly int $id;

    #[ORM\Column(name: 'doc_id', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false)]
    private readonly int $docId;

    #[ORM\Column(name: 'code', type: \Doctrine\DBAL\Types\Types::STRING, length: 50, nullable: false)]
    private readonly string $code;

    #[ORM\Column(name: 'name', type: \Doctrine\DBAL\Types\Types::STRING, length: 200, nullable: false)]
    private readonly string $name;

    #[ORM\Column(name: 'value', type: \Doctrine\DBAL\Types\Types::STRING, length: 500, nullable: false)]
    private readonly string $value;

    #[ORM\Column(name: 'link', type: \Doctrine\DBAL\Types\Types::STRING, length: 750, nullable: false)]
    private readonly string $link;

    #[ORM\Column(name: 'source_id', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false)]
    private readonly int $sourceId;

    /**
     * @var DateTime
     */
    #[ORM\Column(name: 'time', type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE, nullable: false)]
    private \DateTimeInterface $time;
    public function __construct()
    {
        $this->time = new \DateTime();
    }


}
