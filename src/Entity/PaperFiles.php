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
#[ORM\Index(name: 'doc_id', columns: ['doc_id'])]
class PaperFiles
{
    /**
     * @var int
     */
    #[ORM\Column(name: 'id', type: 'integer', nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private readonly int $id;

    /**
     * @var int
     */
    #[ORM\Column(name: 'doc_id', type: 'integer', nullable: false, options: ['unsigned' => true])]
    private readonly int $docId;

    /**
     * @var string
     */
    #[ORM\Column(name: 'file_name', type: 'string', length: 500, nullable: false)]
    private readonly string $fileName;

    /**
     * @var string
     */
    #[ORM\Column(name: 'checksum', type: 'string', length: 32, nullable: false, options: ['fixed' => true])]
    private readonly string $checksum;

    /**
     * @var string
     */
    #[ORM\Column(name: 'checksum_type', type: 'string', length: 10, nullable: false, options: ['fixed' => true])]
    private readonly string $checksumType;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'self_link', type: 'string', length: 750, nullable: true)]
    private ?string $selfLink = null;

    /**
     * @var int
     */
    #[ORM\Column(name: 'file_size', type: 'bigint', nullable: false, options: ['unsigned' => true])]
    private readonly int $fileSize;

    /**
     * @var string
     */
    #[ORM\Column(name: 'file_type', type: 'string', length: 20, nullable: false)]
    private readonly string $fileType;

    /**
     * @var DateTime|null
     */
    #[ORM\Column(name: 'time_modified', type: 'datetime', nullable: true, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $timeModified = null;


}
