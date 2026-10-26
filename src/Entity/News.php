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
use App\Repository\NewsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Table(name: self::TABLE)]
#[ORM\Index(columns: ['uid'], name: 'uid')]
#[ORM\Entity(repositoryClass: NewsRepository::class)]

#[ApiResource(
    uriTemplate: '/news/',
    operations: [
        new GetCollection(

            openapi: new OpenApiOperation(
                summary: 'List of News',
                description: 'Retrieve a list of news published by the journals, sorted in descending order of creation (most recent first). Can be filtered by the year of creation.',
                parameters: [
                    new Parameter(
                        name: AppConstants::YEAR_PARAM,
                        in: 'query',
                        description: 'The Year of creation',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: false,
                        schema: [
                            'type' => 'integer',
                        ],explode: false, allowReserved: false
                    ),
                    new Parameter(
                        name: 'year[]',
                        in: 'query',
                        description: 'The year(s) of creation, repeated for each year (e.g. ?year[]=2023&year[]=2024)',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: false,
                        schema: [
                            'type' => 'array',
                            'items' => [
                                'type' => 'integer',
                            ]
                        ],
                        explode: true,
                        allowReserved: false,

                    ),
                ]
            ),
            order: ['date_creation' => AppConstants::ORDER_DESC],

            normalizationContext: [
                'groups' => ['read:News:Collection']
            ]

        ),
    ],

)]

#[ApiResource(
    uriTemplate: '/news/{id}',
    operations: [
        new Get(
            uriTemplate: '/news/{id}',
            openapi: new OpenApiOperation(
                summary: 'Single News',
                description: 'Retrieve a single news item by replacing {id} with the news identifier',
            ),
            normalizationContext: [
                'groups' => ['read:News']
            ],
        ),
    ],
)]

#[ApiFilter(
    SearchFilter::class,
    properties: self::FILTERS
)]
class News
{
    public const TABLE = 'news';

    public const FILTERS = [
        'rvcode' => AppConstants::FILTER_TYPE_EXACT,
        'news_code' => AppConstants::FILTER_TYPE_EXACT,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[ApiProperty(identifier: true)]
    #[groups(
        ['read:News', 'read:News:Collection']
    )]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'news')]
    #[ORM\JoinColumn(name: 'UID', referencedColumnName: 'UID', nullable: false)]
    #[groups(
        ['read:News', 'read:News:Collection']
    )]
    // Public: no IRI, it would expose the account identifier
    #[ApiProperty(genId: false, description: 'User who created the news (embedded user information, not an IRI to avoid exposing the account identifier)')]
    private ?User $creator = null;

    #[ORM\Column(nullable: true)]
    private ?int $legacy_id = null;
    #[ORM\Column(name: 'code', length: 100, nullable: false, options: ['comment' => 'Journal code rvcode'])]
    #[groups(
        ['read:News', 'read:News:Collection']
    )]
    #[ApiProperty(description: 'Code of the journal the news belongs to (e.g. "epijinfo")')]
    private ?string $rvcode = null;

    #[ORM\Column(name: 'uid', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    private ?int $uid = null;

    #[ORM\Column(name: 'title', type: \Doctrine\DBAL\Types\Types::JSON, nullable: false, options: ['comment' => 'Page title'])]
    #[groups(
        ['read:News', 'read:News:Collection']
    )]
    #[ApiProperty(description: 'News title translated by language code', schema: OpenApiSchemas::TRANSLATED_TEXT)]
    private array $title = [];

    #[ORM\Column(name: 'content', type: \Doctrine\DBAL\Types\Types::JSON, nullable: true)]
    #[groups(
        ['read:News', 'read:News:Collection']
    )]
    #[ApiProperty(description: 'Full news content (HTML), translated by language code', schema: OpenApiSchemas::NULLABLE_TRANSLATED_TEXT)]
    private ?array $content = null;

    #[ORM\Column(nullable: true)]
    #[groups(
        ['read:News', 'read:News:Collection']
    )]
    #[ApiProperty(description: 'External link associated with the news, translated by language code', schema: OpenApiSchemas::NULLABLE_TRANSLATED_TEXT)]
    private ?array $link = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[groups(
        ['read:News', 'read:News:Collection']
    )]
    #[ApiProperty(description: 'Date the news was created', openapiContext: ['example' => '2024-01-15T10:30:00+00:00'])]
    private ?\DateTimeInterface $date_creation = null;

    #[ORM\Column(name: 'date_updated', type: Types::DATETIME_MUTABLE)]
    #[groups(
        ['read:News', 'read:News:Collection']
    )]
    #[ApiProperty(description: 'Date the news was last updated', openapiContext: ['example' => '2024-01-15T10:30:00+00:00'])]
    private ?\DateTimeInterface $date_updated = null;

    #[ORM\Column]
    #[groups(
        ['read:News']
    )]
    #[ApiProperty(description: 'Audience of the news: index 0 indicates its status, e.g. ["public"]', schema: ['type' => 'array', 'maxItems' => 1, 'items' => ['type' => 'string'], 'example' => ['public']])]
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

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLegacyId(): ?int
    {
        return $this->legacy_id;
    }

    public function setLegacyId(?int $legacy_id): static
    {
        $this->legacy_id = $legacy_id;

        return $this;
    }

    public function getRvcode(): ?string
    {
        return $this->rvcode;
    }

    public function setRvcode(string $rvcode): static
    {
        $this->rvcode = $rvcode;

        return $this;
    }

    public function getUid(): ?int
    {
        return $this->uid;
    }

    public function setUid(int $uid): static
    {
        $this->uid = $uid;

        return $this;
    }

    public function getDateCreation(): ?\DateTimeInterface
    {
        return $this->date_creation;
    }

    public function setDateCreation(?\DateTimeInterface $date_creation): static
    {
        $this->date_creation = $date_creation;

        return $this;
    }

    public function getDateUpdated(): ?\DateTimeInterface
    {
        return $this->date_updated;
    }

    public function setDateUpdated(\DateTimeInterface $date_updated): static
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

    public function getContent(): ?array
    {
        return $this->content;
    }

    public function setContent(?array $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getLink(): ?array
    {
        return $this->link;
    }

    public function setLink(?array $link): static
    {
        $this->link = $link;

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

    public function getCreator(): ?User
    {
        return $this->creator;
    }

    public function setCreator(?User $creator): static
    {
        $this->creator = $creator;

        return $this;
    }
}
