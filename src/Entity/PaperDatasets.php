<?php

declare(strict_types=1);

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
    /**
     * @var int
     */
    #[ORM\Column(name: 'id', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private int $id;

    /**
     * @var int
     */
    #[ORM\Column(name: 'doc_id', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false)]
    private int $docId;

    /**
     * @var string
     */
    #[ORM\Column(name: 'code', type: \Doctrine\DBAL\Types\Types::STRING, length: 50, nullable: false)]
    private string $code;

    /**
     * @var string
     */
    #[ORM\Column(name: 'name', type: \Doctrine\DBAL\Types\Types::STRING, length: 200, nullable: false)]
    private string $name;

    /**
     * @var string
     */
    #[ORM\Column(name: 'value', type: \Doctrine\DBAL\Types\Types::STRING, length: 500, nullable: false)]
    private string $value;

    /**
     * @var string
     */
    #[ORM\Column(name: 'link', type: \Doctrine\DBAL\Types\Types::STRING, length: 750, nullable: false)]
    private string $link;

    /**
     * @var int
     */
    #[ORM\Column(name: 'source_id', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false)]
    private int $sourceId;

    #[ORM\Column(name: 'time', type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE, nullable: false)]
    private readonly \DateTime $time;
    public function __construct()
    {
        $this->time = new \DateTime('CURRENT_TIMESTAMP');
    }


}
