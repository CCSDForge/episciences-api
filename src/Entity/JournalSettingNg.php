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
                                        'properties' => new \ArrayObject([

                                            'menu' => [
                                                'type' => 'object',
                                                'additionalProperties' => false, // uniquement les propriétés déclarées sont autorisées
                                                'example' => [
                                                    'authorsRender' => true,
                                                    'sectionsRender' => true,
                                                    'specialIssuesRender' => true,
                                                    'journalIndexingRender' => false,
                                                    'acceptedArticlesRender' => true,
                                                    'journalForReviewersRender' => false,
                                                    'journalEthicalCharterRender' => true,
                                                    'volumeTypeProceedingsRender' => true,
                                                    'journalAcknowledgementsRender' => false,
                                                    'journalProposingSpecialIssuesRender' => false,
                                                    'journalForConferenceOrganisersRender' => false
                                                ]
                                            ],
                                            'theme' => [
                                                'type' => 'object',
                                                'additionalProperties' => false,
                                                'example' => [
                                                    'primaryColor' => '#49737e',
                                                    'primaryTextColor' => '#ffffff'
                                                ]

                                            ],
                                            'homepage ' => [
                                                'type' => 'object',
                                                'additionalProperties' => false,
                                                'example' => [

                                                    'statsRender ' => true,
                                                    'lastNewsRender ' => true,
                                                    'specialIssuesRender ' => true,
                                                    'membersCarouselRender ' => true,
                                                    'journalIndexationRender ' => true,
                                                    'latestNewsCarouselRender ' => true,
                                                    'latestArticlesCarouselRender ' => true,
                                                    'latestAcceptedArticlesCarouselRender ' => true
                                                ]
                                            ],
                                            'languages ' => [
                                                'type' => 'object',
                                                'additionalProperties' => false,
                                                'example' => [
                                                    'default ' => 'en ',
                                                    'accepted ' => [
                                                        'en ',
                                                        'fr ',
                                                        'es '
                                                    ]
                                                ]
                                            ],
                                            'api_domain' => [
                                                'type' => 'string',
                                                'example' => 'api-dev.episciences.org'
                                            ],

                                            'statistics ' => [
                                                'type' => 'object',
                                                'additionalProperties' => false,
                                                'example' => [

                                                    'colors ' => [
                                                        '#840909 ',
                                                        '#295fba ',
                                                        '#3f557a ',
                                                        '#192132 '
                                                    ],
                                                    'nbSubmissions ' => [
                                                        'order ' => 2,
                                                        'render ' => true
                                                    ],
                                                    'acceptanceRate ' => [
                                                        'order ' => 10,
                                                        'render ' => true
                                                    ],
                                                    'reviewsReceived ' => [
                                                        'order ' => 2,
                                                        'render ' => true
                                                    ],
                                                    'reviewsRequested ' => [
                                                        'order ' => 1,
                                                        'render ' => true
                                                    ],
                                                    'medianReviewsNumber ' => [
                                                        'order ' => 4,
                                                        'render ' => true
                                                    ],
                                                    'nbSubmissionsDetails ' => [
                                                        'order ' => 3,
                                                        'render ' => true
                                                    ],
                                                    'medianSubmissionPublication ' => [
                                                        'order ' => 3,
                                                        'render ' => true
                                                    ]
                                                ]
                                            ],

                                            'homepageRightBlock ' => [
                                                'type' => 'string',
                                                'additionalProperties' => false,
                                                'example' => [
                                                    'lastInformationRenderType ' => 'last-news '
                                                ]
                                            ],


                                        ])

                                    ]


                                )
                            )
                        ])

                    )


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
                security: [['bearerAuth' => []],]
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

    #[ORM\Column(name: 'ID', type: 'integer', nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ApiProperty(identifier: false)]
    private int $id;

    #[ORM\Column(name: 'RVID', type: 'integer', unique: true)]
    private ?int $rvid = null;
    #[ORM\Column(name: 'SETTING', type: 'json', nullable: false)]
    private array $settings;

    #[ORM\Column(name: 'CREATED_AT', type: 'datetime', nullable: true, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $createdAt = null;


    #[ORM\Column(name: 'UPDATED_AT', type: 'datetime', nullable: true, options: ['default' => 'CURRENT_TIMESTAMP'])]
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
