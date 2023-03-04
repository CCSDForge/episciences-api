<?php

namespace App\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * PaperDatasets
 *
 * @ORM\Table(name="paper_datasets", uniqueConstraints={@ORM\UniqueConstraint(name="unique", columns={"doc_id", "code", "name", "value", "source_id"})}, indexes={@ORM\Index(name="code", columns={"code"}), @ORM\Index(name="doc_id", columns={"doc_id"}), @ORM\Index(name="name", columns={"name"}), @ORM\Index(name="source_id", columns={"source_id"})})
 * @ORM\Entity
 */
class PaperDatasets
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private int $id;

    /**
     * @var int
     *
     * @ORM\Column(name="doc_id", type="integer", nullable=false)
     */
    private int $docId;

    /**
     * @var string
     *
     * @ORM\Column(name="code", type="string", length=50, nullable=false)
     */
    private string $code;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=200, nullable=false)
     */
    private string $name;

    /**
     * @var string
     *
     * @ORM\Column(name="value", type="string", length=500, nullable=false)
     */
    private string $value;

    /**
     * @var string
     *
     * @ORM\Column(name="link", type="string", length=750, nullable=false)
     */
    private string $link;

    /**
     * @var int
     *
     * @ORM\Column(name="source_id", type="integer", nullable=false)
     */
    private int $sourceId;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="time", type="datetime", nullable=false, options={"default"="CURRENT_TIMESTAMP"})
     */
    private $time = 'CURRENT_TIMESTAMP';


}
