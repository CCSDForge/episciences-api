<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Parameter;
use App\OpenApi\OpenApiFactory;
use App\Repository\JournalSettingNgRepository;
use App\State\JournalSettingNgProvider;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\OpenApi\Model\Response;

#[ORM\Table(name: self::TABLE)]
#[ORM\UniqueConstraint(name: 'RVID', columns: ['RVID'])]
#[ORM\Entity(repositoryClass: JournalSettingNgRepository::class)]
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: Review::URI_TEMPLATE . 'front/configuration',
            openapi: new OpenApiOperation(
                tags: [OpenApiFactory::OAF_TAGS['review']],
                responses: [
                    '200' => new Response(
                        description: 'Configuration flags',
                        content: new \ArrayObject([
                            'application/json' => new MediaType(
                                schema: new \ArrayObject([
                                    'type' => 'object',
                                    'properties' => [
                                        'api_domain' => ['type' => 'string', 'example' => 'api-dev.episciences.org'],
                                        'theme' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'primaryColor' => self::OAS_COLOR + ['example' => '#49737e'],
                                                'primaryTextColor' => self::OAS_COLOR + ['example' => '#ffffff'],
                                            ],
                                        ],
                                        'languages' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'default' => self::OAS_LANGUAGE + ['example' => 'en'],
                                                'accepted' => ['type' => 'array', 'minItems' => 1, 'items' => self::OAS_LANGUAGE, 'example' => ['en', 'fr']],
                                            ],
                                        ],
                                        'homepage' => [
                                            'type' => 'object',
                                            'properties' => [
                                                    'latestArticlesCarouselRender' => self::OAS_BOOLEAN,
                                                    'latestNewsCarouselRender' => self::OAS_BOOLEAN,
                                                    'membersCarouselRender' => self::OAS_BOOLEAN,
                                                    'statsRender' => self::OAS_BOOLEAN,
                                                    'journalIndexationRender' => self::OAS_BOOLEAN,
                                                    'specialIssuesRender' => self::OAS_BOOLEAN,
                                                    'latestAcceptedArticlesCarouselRender' => self::OAS_BOOLEAN,
                                                    'lastNewsRender' => self::OAS_BOOLEAN,
                                            ],
                                        ],
                                        'homepageRightBlock' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'lastInformationRenderType' => [
                                                    'type' => 'string',
                                                    'enum' => ['last-news', 'last-volume', 'last-special-issue'],
                                                    'example' => 'last-news',
                                                ],
                                            ],
                                        ],
                                        'menu' => [
                                            'type' => 'object',
                                            'properties' => [
                                                    'acceptedArticlesRender' => self::OAS_BOOLEAN,
                                                    'volumeTypeProceedingsRender' => self::OAS_BOOLEAN,
                                                    'specialIssuesRender' => self::OAS_BOOLEAN,
                                                    'sectionsRender' => self::OAS_BOOLEAN,
                                                    'authorsRender' => self::OAS_BOOLEAN,
                                                    'journalIndexingRender' => self::OAS_BOOLEAN,
                                                    'journalAcknowledgementsRender' => self::OAS_BOOLEAN,
                                                    'journalEthicalCharterRender' => self::OAS_BOOLEAN,
                                                    'journalForReviewersRender' => self::OAS_BOOLEAN,
                                                    'journalForEditorsRender' => self::OAS_BOOLEAN,
                                                    'journalForConferenceOrganisersRender' => self::OAS_BOOLEAN,
                                                    'journalProposingSpecialIssuesRender' => self::OAS_BOOLEAN,
                                            ],
                                        ],
                                        'statistics' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'colors' => ['type' => 'array', 'items' => self::OAS_COLOR, 'example' => ['#840909', '#295fba', '#3f557a', '#192132']],
                                                    'acceptanceRate' => self::OAS_STAT_OPTION,
                                                    'nbSubmissions' => self::OAS_STAT_OPTION,
                                                    'nbSubmissionsDetails' => self::OAS_STAT_OPTION,
                                                    'reviewsRequested' => self::OAS_STAT_OPTION,
                                                    'reviewsReceived' => self::OAS_STAT_OPTION,
                                                    'medianSubmissionPublication' => self::OAS_STAT_OPTION,
                                                    'medianReviewsNumber' => self::OAS_STAT_OPTION,
                                            ],
                                        ],
                                    ],
                                ])
                            ),
                        ])
                    ),
                ],
                summary: 'Retrieves the public interface configuration',
                description: 'Retrieve the public interface configuration for sites migrated to the new interfaces',
                parameters: [new Parameter(
                    name: 'code',
                    in: 'query',
                    description: 'Journal Code (ex. epijinfo)',
                    required: true,
                    schema: [
                        'type' => 'string',
                    ]
                )

                ],
            ),

            normalizationContext: [
                'groups' => ['read:ng:setting'],
                'serialize_null' => true
            ],

        ),
    ],
    formats: ['json'],
    provider: JournalSettingNgProvider::class
)]
class JournalSettingNg
{
    public const TABLE = 'JOURNAL_SETTING';

    // OpenAPI schema fragments, aligned with the settings JSON schema of episciences-manager
    private const array OAS_BOOLEAN = ['type' => 'boolean'];
    private const array OAS_COLOR = ['type' => 'string', 'pattern' => '^#[0-9A-Fa-f]{6}$'];
    private const array OAS_LANGUAGE = ['type' => 'string', 'enum' => ['en', 'fr', 'es', 'de']];
    private const array OAS_STAT_OPTION = [
        'type' => 'object',
        'properties' => [
            'render' => self::OAS_BOOLEAN,
            'order' => ['type' => 'integer', 'minimum' => 0],
        ],
    ];

    #[ORM\Column(name: 'ID', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ApiProperty(identifier: false)]
    private int $id;

    #[ORM\Column(name: 'RVID', type: \Doctrine\DBAL\Types\Types::INTEGER, unique: true)]
    private int $rvid;

    #[ORM\Column(name: 'SETTING', type: \Doctrine\DBAL\Types\Types::JSON, nullable: false)]
    private array $settings;

    #[ORM\Column(name: 'CREATED_AT', type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE, nullable: true, options: ['default' => new \Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp()])]
    private ?\DateTimeInterface $createdAt = null;


    #[ORM\Column(name: 'UPDATED_AT', type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE, nullable: true, options: ['default' => new \Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp()])]
    private ?\DateTimeInterface $updatedAt = null;


    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return JournalSettingNg
     */
    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getRvid(): int
    {
        return $this->rvid;
    }

    public function setRvid(int $rvid): self
    {
        $this->rvid = $rvid;
        return $this;
    }

    /**
     * @return array
     */
    public function getSettings(): array
    {
        return $this->settings;
    }

    /**
     * @param array $settings
     * @return JournalSettingNg
     */
    public function setSettings(array $settings): self
    {
        $this->settings = $settings;
        return $this;
    }

    /**
     * @return \DateTimeInterface|null
     */
    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * @param \DateTimeInterface|null $createdAt
     * @return JournalSettingNg
     */
    public function setCreatedAt(?\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return \DateTimeInterface|null
     */
    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    /**
     * @param \DateTimeInterface|null $updatedAt
     * @return JournalSettingNg
     */
    public function setUpdatedAt(?\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

}
