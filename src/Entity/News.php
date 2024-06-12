<?php

namespace App\Entity;

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
use App\State\NewsStateProvider;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Table(name: self::TABLE)]
#[ORM\UniqueConstraint(name: 'uid', columns: ['uid'])]
#[ORM\UniqueConstraint(name: 'rvcode', columns: ['code'])]
#[ORM\Entity(repositoryClass: NewsRepository::class)]
#[ApiResource(
    operations: [

        new GetCollection(
            openapi: new OpenApiOperation(
                summary: 'List of News',
                description: 'Retrieving a list of News',
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
                        ]
                    ),
                    ]
            ),
            normalizationContext: [
                'groups' => ['read:News:Collection']
            ],
            provider: NewsStateProvider::class,

        ),
        new Get(
            openapi: new OpenApiOperation(
                summary: 'Single News',
                description: 'Retrieve a single News via a GET request by replacing {id} with News identifier',
            ),
            normalizationContext: [
                'groups' => ['read:News']
            ],
        ),

    ],
    order: ['date_creation' => AppConstants::ORDER_DESC]
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

    //#[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'news')]
    //#[ORM\JoinColumn(name: 'UID', referencedColumnName: 'UID', nullable: false)]

    private ?User $creator = null;

    #[ORM\Column(nullable: true)]
    private ?int $legacy_id = null;
    #[ORM\Column(name: 'code', length: 100, nullable: false, options: ['comment' => 'Journal code rvcode'])]
    #[groups(
        ['read:News', 'read:News:Collection']
    )]
    private ?string $rvcode = null;

    #[ORM\Column(name: 'uid', type: 'integer', nullable: false)]
    private ?int $uid = null;

    #[ORM\Column(name: 'title', type: 'json', nullable: false, options: ['comment' => 'Page title'])]
    #[groups(
        ['read:News', 'read:News:Collection']
    )]
    private array $title = [];

    #[ORM\Column(name: 'content', type: 'json', nullable: true)]
    #[groups(
        ['read:News', 'read:News:Collection']
    )]
    private ?array $content = null;

    #[ORM\Column(nullable: true)]
    #[groups(
        ['read:News', 'read:News:Collection']
    )]
    private ?array $link = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[groups(
        ['read:News', 'read:News:Collection']
    )]
    private ?\DateTimeInterface $date_creation = null;

    #[ORM\Column(name: 'date_updated', type: Types::DATETIME_MUTABLE)]
    #[groups(
        ['read:News', 'read:News:Collection']
    )]
    private ?\DateTimeInterface $date_updated = null;

    #[ORM\Column]
    #[groups(
        ['read:News']
    )]
    private array $visibility = [];

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
