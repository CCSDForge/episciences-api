<?php

declare(strict_types=1);

namespace App\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * PaperFiles
 */
#[ORM\Entity]
#[ORM\Table(name: 'paper_files')]
#[ORM\Index(columns: ['doc_id'], name: 'doc_id')]
class PaperFiles
{
    /**
     * @var int
     */
    #[ORM\Column(name: 'id', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private int $id;

    /**
     * @var int
     */
    #[ORM\Column(name: 'doc_id', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    private int $docId;

    /**
     * @var string
     */
    #[ORM\Column(name: 'file_name', type: \Doctrine\DBAL\Types\Types::STRING, length: 500, nullable: false)]
    private string $fileName;

    /**
     * @var string
     */
    #[ORM\Column(name: 'checksum', type: \Doctrine\DBAL\Types\Types::STRING, length: 32, nullable: false, options: ['fixed' => true])]
    private string $checksum;

    /**
     * @var string
     */
    #[ORM\Column(name: 'checksum_type', type: \Doctrine\DBAL\Types\Types::STRING, length: 10, nullable: false, options: ['fixed' => true])]
    private string $checksumType;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'self_link', type: \Doctrine\DBAL\Types\Types::STRING, length: 750, nullable: true)]
    private ?string $selfLink = null;

    /**
     * @var int
     */
    #[ORM\Column(name: 'file_size', type: \Doctrine\DBAL\Types\Types::BIGINT, nullable: false, options: ['unsigned' => true])]
    private int $fileSize;

    /**
     * @var string
     */
    #[ORM\Column(name: 'file_type', type: \Doctrine\DBAL\Types\Types::STRING, length: 20, nullable: false)]
    private string $fileType;

    /**
     * @var DateTime|null
     */
    #[ORM\Column(name: 'time_modified', type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE, nullable: true, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $timeModified = null;


}
