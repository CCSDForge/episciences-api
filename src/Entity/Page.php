<?php

namespace App\Entity;

use App\OpenApi\OpenApiSchemas;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Parameter;
use App\AppConstants;
use App\Repository\PageRepository;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Table(name: self::TABLE)]
#[ORM\Entity(repositoryClass: PageRepository::class)]
#[ORM\UniqueConstraint(name: 'uid', columns: ['uid'])]
#[ORM\UniqueConstraint(name: 'rvcode_page_code', columns: ['code', 'page_code'])]
#[ApiResource(
    operations: [

        new GetCollection(
            openapi: new OpenApiOperation(
                summary: 'List of pages',
                description: 'Retrieve a list of the editorial pages of the journals (about, for authors, editorial board, etc.), with their translated title. Filter by journal code or page code.',
                parameters: [
                    new Parameter(
                        name: 'pagination',
                        in: 'query',
                        description: 'Enable or disable pagination',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: true,
                        schema: [
                            'type' => 'boolean',
                        ],
                        style: 'form',
                        explode: false,
                        allowReserved: false

                    ),
                ]
            ),
            normalizationContext: [
                'groups' => ['read:Pages']
            ],

        ),
        new Get(
            openapi: new OpenApiOperation(
                summary: 'Single page',
                description: 'Retrieve a single editorial page (with its translated content) by replacing {id} with the page identifier',
            ),

            normalizationContext: [
                'groups' => ['read:Page']
            ],
        ),

    ]
)]
#[ApiFilter(
    SearchFilter::class,
    properties: self::FILTERS
)]
class Page
{
    public const TABLE = 'pages';
    public const FILTERS = [
        'uid' => AppConstants::FILTER_TYPE_EXACT,
        'rvcode' => AppConstants::FILTER_TYPE_EXACT,
        'page_code' => AppConstants::FILTER_TYPE_EXACT,
    ];
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[groups(
        ['read:Page', 'read:Pages']
    )]
    #[ApiProperty(identifier: true)]
    private int $id;


    #[ORM\Column(name: 'uid', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false)]
    private int $uid;


    #[ORM\Column(name: 'page_code', length: 255, nullable: false)]
    #[groups(
        ['read:Page', 'read:Pages']
    )]
    #[ApiProperty(description: 'Machine-readable code identifying the page within the journal (e.g. "about", "for-authors")')]
    private string $page_code;


    #[ORM\Column(name: 'code', length: 100, nullable: false, options: ['comment' => 'Journal code rvcode'])]
    #[groups(
        ['read:Page', 'read:Pages']
    )]
    #[ApiProperty(description: 'Code of the journal the page belongs to (e.g. "epijinfo")')]
    private string $rvcode;

    #[ORM\Column(name: 'title', type: \Doctrine\DBAL\Types\Types::JSON, nullable: false)]
    #[groups(
        ['read:Page', 'read:Pages']
    )]
    #[ApiProperty(description: 'Page title translated by language code', schema: OpenApiSchemas::TRANSLATED_TEXT)]
    private array $title = [];

    #[ORM\Column(name: 'content', type: \Doctrine\DBAL\Types\Types::JSON, nullable: false)]
    #[groups(
        ['read:Page', 'read:Pages']
    )]
    #[ApiProperty(description: 'Page content (HTML), translated by language code', schema: OpenApiSchemas::TRANSLATED_TEXT)]
    private array $content = [];

    #[ORM\Column(name: 'visibility', type: \Doctrine\DBAL\Types\Types::JSON, nullable: false)]
    #[groups(
        ['read:Page']
    )]
    #[ApiProperty(description: 'Audience of the page: index 0 indicates its status, e.g. ["public"]', schema: ['type' => 'array', 'maxItems' => 1, 'items' => ['type' => 'string'], 'example' => ['public']])]
    private array $visibility = [];

    /**
     * MySQL stored generated column: 1 when visibility[0] = 'public', NULL otherwise.
     * Indexed — used instead of JSON_EXTRACT() in WHERE clauses to avoid full-table scans.
     */
    #[ORM\Column(
        name: 'is_public',
        nullable: true,
        insertable: false,
        updatable: false,
        columnDefinition: "TINYINT(1) GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(visibility, '\$[0]')) = 'public') STORED"
    )]
    private ?bool $is_public = null;


    #[ORM\Column(name: 'date_creation', type: Types::DATETIME_MUTABLE, nullable: true)]
    #[groups(
        ['read:Page', 'read:Pages']
    )]
    #[ApiProperty(description: 'Date the page was created', openapiContext: ['example' => '2024-01-15T10:30:00+00:00'])]
    private ?DateTimeInterface $date_creation = null;

    #[ORM\Column(name: 'date_updated', type: Types::DATETIME_MUTABLE, nullable: false)]
    #[groups(
        ['read:Page', 'read:Pages']
    )]
    #[ApiProperty(description: 'Date the page was last updated', openapiContext: ['example' => '2024-01-15T10:30:00+00:00'])]
    private DateTimeInterface $date_updated;

    public function getId(): int
    {
        return $this->id;
    }

    public function getRvcode(): string
    {
        return $this->rvcode;
    }

    public function setRvcode(string $rvcode): static
    {
        $this->rvcode = $rvcode;

        return $this;
    }

    public function getUid(): int
    {
        return $this->uid;
    }

    public function setUid(int $uid): static
    {
        $this->uid = $uid;

        return $this;
    }

    public function getDateCreation(): ?DateTimeInterface
    {
        return $this->date_creation;
    }

    public function setDateCreation(?DateTimeInterface $date_creation): static
    {
        $this->date_creation = $date_creation;

        return $this;
    }

    public function getDateUpdated(): ?DateTimeInterface
    {
        return $this->date_updated;
    }

    public function setDateUpdated(DateTimeInterface $date_updated): static
    {
        $this->date_updated = $date_updated;

        return $this;
    }

    public function getTitle(): array
    {
        return $this->title;
    }

    public function setTitle(array $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getContent(): array
    {
        return $this->content;
    }

    public function setContent(array $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getVisibility(): array
    {
        return $this->visibility;
    }

    public function setVisibility(array $visibility): static
    {
        $this->visibility = $visibility;

        return $this;
    }

    public function isPublic(): ?bool
    {
        return $this->is_public;
    }

    public function getPageCode(): ?string
    {
        return $this->page_code;
    }

    public function setPageCode(string $page_code): static
    {
        $this->page_code = $page_code;

        return $this;
    }
}
