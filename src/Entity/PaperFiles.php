<?php

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
    #[ORM\Column(name: 'id', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private readonly int $id;

    #[ORM\Column(name: 'doc_id', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    private readonly int $docId;

    #[ORM\Column(name: 'file_name', type: \Doctrine\DBAL\Types\Types::STRING, length: 500, nullable: false)]
    private readonly string $fileName;

    #[ORM\Column(name: 'checksum', type: \Doctrine\DBAL\Types\Types::STRING, length: 32, nullable: false, options: ['fixed' => true])]
    private readonly string $checksum;

    #[ORM\Column(name: 'checksum_type', type: \Doctrine\DBAL\Types\Types::STRING, length: 10, nullable: false, options: ['fixed' => true])]
    private readonly string $checksumType;

    #[ORM\Column(name: 'self_link', type: \Doctrine\DBAL\Types\Types::STRING, length: 750, nullable: true)]
    private ?string $selfLink = null;

    #[ORM\Column(name: 'file_size', type: \Doctrine\DBAL\Types\Types::BIGINT, nullable: false, options: ['unsigned' => true])]
    private readonly int $fileSize;

    #[ORM\Column(name: 'file_type', type: \Doctrine\DBAL\Types\Types::STRING, length: 20, nullable: false)]
    private readonly string $fileType;

    /**
     * @var DateTime|null
     */
    #[ORM\Column(name: 'time_modified', type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE, nullable: true, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $timeModified = null;


}
