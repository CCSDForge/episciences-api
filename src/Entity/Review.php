<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\Response as OpenApiResponse;
use App\AppConstants;
use App\Controller\BoardsController;
use App\Controller\FeedController;
use App\Resource\BoardMember;
use App\Resource\DashboardOutput;
use App\Resource\SubmissionAcceptanceDelayOutput;
use App\Resource\SubmissionOutput;
use App\Resource\SubmissionPublicationDelayOutput;
use App\Resource\UsersStatsOutput;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\DataProvider\ReviewStatsDataProvider;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use App\Repository\ReviewRepository;
use App\OpenApi\OpenApiFactory;
use App\Resource\Statistic;
use App\Controller\IndexingDatabaseController;
use App\Entity\IndexingDatabase;


#[ORM\Table(name: self::TABLE)]
#[ORM\UniqueConstraint(name: 'U_CODE', columns: ['CODE'])]
#[ORM\Entity(repositoryClass: ReviewRepository::class)]
#[ApiResource(
    uriTemplate: self::URI_TEMPLATE . '{code}',
    operations: [
        new GetCollection(
            uriTemplate: self::URI_TEMPLATE . 'boards/{code}',
            openapi: new OpenApiOperation(
                tags: [OpenApiFactory::OAF_TAGS['review']],
                summary: 'Boards',
                description: 'Editorial board',
            ),

            normalizationContext: [
                'groups' => ['read:Boards'],
                'serialize_null' => true
            ],
            read: false,
        ),
    ],
    // The html format renders Swagger UI, which is not supported by operations served by a custom controller
    formats: ['jsonld', 'json', 'xml'],
    controller: BoardsController::class,
    // The controller returns a public view of the board members
    output: BoardMember::class

)]
#[ApiResource(
    uriTemplate: '/feed/rss/{code}',
    operations: [
        new GetCollection(
            openapi: new OpenApiOperation(
                tags: ['Feed'],
                responses: [
                    '200' => new OpenApiResponse(
                        description: 'RSS feed',
                        content: new \ArrayObject(['text/xml' => new MediaType(schema: new \ArrayObject(['type' => 'string']))])
                    ),
                ],
                summary: 'Feed RSS',
                description: 'An RSS Feed of the latest publications',
            ),
            paginationEnabled: false,
            paginationClientEnabled: false,
            paginationClientItemsPerPage: false,
            normalizationContext: [
                'groups' => ['read:Feed'],
                'serialize_null' => true
            ],
            read: false,

        ),
    ],
    formats: ['xml' => ['text/xml', 'application/xml']],
    controller: FeedController::class

)]
#[ApiResource(
    uriTemplate: '/feed/atom/{code}',
    operations: [
        new GetCollection(
            openapi: new OpenApiOperation(
                tags: ['Feed'],
                responses: [
                    '200' => new OpenApiResponse(
                        description: 'ATOM feed',
                        content: new \ArrayObject(['text/xml' => new MediaType(schema: new \ArrayObject(['type' => 'string']))])
                    ),
                ],
                summary: 'Feed ATOM',
                description: 'An ATOM Feed of the latest publications',
            ),
            paginationEnabled: false,
            paginationClientEnabled: false,
            paginationClientItemsPerPage: false,
            normalizationContext: [
                'groups' => ['read:Feed'],
                'serialize_null' => true
            ],
            read: false,
        ),
    ],
    formats: ['xml' => ['text/xml', 'application/xml']],
    controller: FeedController::class
)]
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: self::URI_TEMPLATE . '{code}',
            openapi: new OpenApiOperation(
                tags: [OpenApiFactory::OAF_TAGS['review']],
                summary: 'Journal',
                description: 'Retrieves a Journal resource.',
            ),

            normalizationContext: [
                'groups' => ['read:Review'],
                'serialize_null' => true
            ],

        #security: "is_granted('ROLE_SECRETARY')",
        ),

        new GetCollection(
            uriTemplate: self::URI_TEMPLATE,
            openapi: new OpenApiOperation(
                tags: [OpenApiFactory::OAF_TAGS['review']],
                summary: 'All Journals',
                description: 'Retrieves the collection of active journals',
            ),
            normalizationContext: [
                'groups' => ['read:Reviews'],
                'serialize_null' => true
            ],


        //security: "is_granted('ROLE_EPIADMIN')",

        ),

        new Get(
            uriTemplate: AppConstants::APP_CONST['custom_operations']['uri_template'][AppConstants::STATS_DASHBOARD_ITEM],
            openapi: new OpenApiOperation(
                tags: [OpenApiFactory::OAF_TAGS['stats']],
                summary: "Dashboard",
                description: 'Dashboard of a journal: submissions, decisions, rates, delays and users (by year with `withDetails`). Follows the setting `displayStatistics` of the journal: hidden by default, public, or reserved for the editorial secretaries of the journal (JWT).',
                responses: [
                    '403' => new OpenApiResponse(description: 'Statistics of the journal hidden, or reserved for its editorial secretaries (`displayStatistics` setting)'),
                ],
                parameters: [
                    new Parameter(
                        name: AppConstants::YEAR_PARAM,
                        in: 'query',
                        description: self::DASHBOARD_YEAR_DESCRIPTION,
                        required: false,
                        deprecated: false,
                        allowEmptyValue: false,
                        schema: [
                            'type' => 'integer',
                        ],
                    ),
                    new Parameter(
                        name: AppConstants::START_AFTER_DATE,
                        in: 'query',
                        description: 'Start statistics after date [YYYY-MM-DD]: a wrong format is rejected (422)',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: true,
                        schema: [
                            'type' => 'string',
                            'format' => 'date',
                        ],
                    ),
                    new Parameter(
                        name: AppConstants::WITH_DETAILS,
                        in: 'query',
                        description: 'More details',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: true,
                        schema: [
                            'type' => 'boolean',
                        ],
                    ),
                    new Parameter(
                        name: Statistic::WITH_PAPERS_PARAM,
                        in: 'query',
                        description: self::DASHBOARD_WITH_PAPERS_DESCRIPTION,
                        required: false,
                        deprecated: false,
                        allowEmptyValue: false,
                        schema: [
                            'type' => 'boolean',
                        ],
                    )
                ]
            ),
            normalizationContext: [
                'groups' => [
                    AppConstants::APP_CONST['normalizationContext']['groups']['review']['item']['read'][0]
                ],
                'serialize_null' => true

            ],
            output: DashboardOutput::class,
            name: AppConstants::APP_CONST['custom_operations']['items']['review'][0],
            provider: ReviewStatsDataProvider::class
        ),

        new Get(
            uriTemplate: AppConstants::APP_CONST['custom_operations']['uri_template'][AppConstants::STATS_NB_SUBMISSIONS_ITEM],
            openapi: new OpenApiOperation(
                tags: [OpenApiFactory::OAF_TAGS['stats']],
                summary: "Total number of submissions",
                description: "Total number of submissions of the journal, optionally by year and with details.",
                parameters: [
                    new Parameter(
                        name: AppConstants::YEAR_PARAM,
                        in: 'query',
                        description: 'Year of submission of the articles',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: false,
                        schema: [
                            'type' => 'integer',
                        ],
                    ),
                    new Parameter(
                        name: AppConstants::START_AFTER_DATE,
                        in: 'query',
                        description: 'Start statistics after date [YYYY-MM-DD]: a wrong format is rejected (422)',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: true,
                        schema: [
                            'type' => 'string',
                            'format' => 'date',
                        ],
                    ),
                    new Parameter(
                        name: AppConstants::WITH_DETAILS,
                        in: 'query',
                        description: 'More details',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: true,
                        schema: [
                            'type' => 'boolean',
                        ],
                    )
                ]
            ),
            normalizationContext: [
                'groups' => [
                    AppConstants::APP_CONST['normalizationContext']['groups']['review']['item']['read'][0]
                ],
                'serialize_null' => true

            ],
            output: SubmissionOutput::class,
            name: AppConstants::APP_CONST['custom_operations']['items']['review'][1],
            provider: ReviewStatsDataProvider::class,
        ),

        new Get(
            uriTemplate: AppConstants::APP_CONST['custom_operations']['uri_template'][AppConstants::STATS_DELAY_SUBMISSION_ACCEPTANCE],
            openapi: new OpenApiOperation(
                tags: [OpenApiFactory::OAF_TAGS['stats']],
                summary: "Average time in days between submission and acceptance",
                description: "Average time in days between submission and acceptance",
                parameters: [
                    new Parameter(
                        name: AppConstants::YEAR_PARAM,
                        in: 'query',
                        description: 'Year of acceptance of the articles',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: false,
                        schema: [
                            'type' => 'integer',
                        ],
                    ),
                    new Parameter(
                        name: AppConstants::START_AFTER_DATE,
                        in: 'query',
                        description: 'Start statistics after date [YYYY-MM-DD]: a wrong format is rejected (422)',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: true,
                        schema: [
                            'type' => 'string',
                            'format' => 'date',
                        ],
                    ),
                    new Parameter(
                        name: AppConstants::WITH_DETAILS,
                        in: 'query',
                        description: 'More details',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: true,
                        schema: [
                            'type' => 'boolean',
                        ],
                    )
                ]
            ),
            normalizationContext: [
                'groups' => [
                    AppConstants::APP_CONST['normalizationContext']['groups']['review']['item']['read'][0]
                ],
                'serialize_null' => true

            ],
            output: SubmissionAcceptanceDelayOutput::class,
            name: AppConstants::APP_CONST['custom_operations']['items']['review'][2],
            provider: ReviewStatsDataProvider::class,
        ),

        new Get(
            uriTemplate: AppConstants::APP_CONST['custom_operations']['uri_template'][AppConstants::STATS_DELAY_SUBMISSION_PUBLICATION],
            openapi: new OpenApiOperation(
                tags: [OpenApiFactory::OAF_TAGS['stats']],
                summary: "Average time in days between submission and publication",
                description: "Average time in days between submission and publication",
                parameters: [
                    new Parameter(
                        name: AppConstants::YEAR_PARAM,
                        in: 'query',
                        description: 'Year of publication of the articles',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: false,
                        schema: [
                            'type' => 'integer',
                        ],
                    ),
                    new Parameter(
                        name: AppConstants::START_AFTER_DATE,
                        in: 'query',
                        description: 'Start statistics after date [YYYY-MM-DD]: a wrong format is rejected (422)',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: true,
                        schema: [
                            'type' => 'string',
                            'format' => 'date',
                        ],
                    ),
                    new Parameter(
                        name: AppConstants::WITH_DETAILS,
                        in: 'query',
                        description: 'More details',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: true,
                        schema: [
                            'type' => 'boolean',
                        ],
                    )
                ]
            ),
            normalizationContext: [
                'groups' => [
                    AppConstants::APP_CONST['normalizationContext']['groups']['review']['item']['read'][0]
                ],
                'serialize_null' => true

            ],
            output: SubmissionPublicationDelayOutput::class,
            name: AppConstants::APP_CONST['custom_operations']['items']['review'][3],
            provider: ReviewStatsDataProvider::class,
        ),
        new Get(
            uriTemplate: AppConstants::APP_CONST['custom_operations']['uri_template'][AppConstants::STATS_NB_USERS],
            openapi: new OpenApiOperation(
                tags: [OpenApiFactory::OAF_TAGS['stats']],
                summary: "Number of users by roles",
                description: "Number of users by roles",
                parameters: [
                    new Parameter(
                        name: AppConstants::WITH_DETAILS,
                        in: 'query',
                        description: 'More details',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: true,
                        schema: [
                            'type' => 'boolean',
                        ]
                    ),
                ]
            ),
            normalizationContext: [
                'groups' => [
                    AppConstants::APP_CONST['normalizationContext']['groups']['review']['item']['read'][0]
                ],
                'serialize_null' => true

            ],
            output: UsersStatsOutput::class,
            name: AppConstants::APP_CONST['custom_operations']['items']['review'][4],
            provider: ReviewStatsDataProvider::class,
        ),
    ],

    denormalizationContext: ['groups' => ['write:Review']],
    openapi: new OpenApiOperation(),
    paginationEnabled: true,
    paginationItemsPerPage: 10,
    paginationMaximumItemsPerPage: 30

)]

#[ApiResource(
    uriTemplate: self::URI_TEMPLATE . 'indexing-database/{code}',
    operations: [
        new GetCollection(
            openapi: new OpenApiOperation(
                tags: [OpenApiFactory::OAF_TAGS['review']],
                summary: 'Indexing Databases',
                description: 'List of indexing databases for a journal (DOAJ, Scopus, etc.)',
            ),
            normalizationContext: [
                'groups' => ['read:IndexingDatabases'],
                'serialize_null' => true
            ],
            read: false,
        ),
    ],
    // The html format renders Swagger UI, which is not supported by operations served by a custom controller
    formats: ['jsonld', 'json', 'xml'],
    controller: IndexingDatabaseController::class,
    output: IndexingDatabase::class
)]


class Review
{
    public const TABLE = 'REVIEW';
    public const PORTAL_ID = 0;
    public const STATUS_DISABLED = 0;
    public const STATUS_ENABLED = 1;
    public const URI_TEMPLATE = '/journals/';
    public const DASHBOARD_WITH_PAPERS_DESCRIPTION = 'Adds `papers` to the value, the papers counted by each figure (one DOCID by paper, its latest version; the delays: the papers of the average and median delays), computed with the same queries as the figures. For the administrators of the journal (JWT); the papers for which the user has declared a conflict of interest are not listed, their number by figure is in `hiddenPapers`';
    public const DASHBOARD_YEAR_DESCRIPTION = 'Year of: submission for `nbSubmissions` and `nbImported`; decision (publication, acceptance, refusal) for `nbPublished`, `nbImportedPublished`, `nbAccepted`, `nbAcceptedNotYetPublished`, `nbRefused` and `rates`; publication or acceptance for the delays; both submission and decision for `total*SubmittedSameYear`. The users are not counted by year';

    #[ORM\Column(name: 'RVID', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['collection']['read'][0],
            'read:Reviews', 'read:Review'
        ]
    )]
    #[ApiProperty(identifier: false, description: 'Identifier of the journal')]
    private int $rvid;


    #[ORM\Column(name: 'CODE', type: \Doctrine\DBAL\Types\Types::STRING, length: 50, nullable: false)]
    #[groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['collection']['read'][0],
            'read:Reviews', 'read:Review', 'read:Browse:Authors'
        ]
    )]
    #[ApiProperty(identifier: true, description: 'Code of the journal (ex. epijinfo)')]
    private string $code;


    #[ORM\Column(name: 'NAME', type: \Doctrine\DBAL\Types\Types::STRING, length: 2000, nullable: false)]
    #[Groups(['read:Reviews', 'read:Review'])]
    #[ApiProperty(description: 'Name of the journal')]
    private string $name;

    #[ORM\Column(name: 'SUBTITLE', type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    #[Groups(['read:Reviews', 'read:Review'])]
    #[ApiProperty(description: 'Subtitle of the journal', readable: true, writable: true)]
    private ?string $subtitle = null;

    #[ORM\Column(name: 'STATUS', type: \Doctrine\DBAL\Types\Types::SMALLINT, nullable: false, options: ['unsigned' => true])]
    #[Groups(['read:Reviews'])]
    #[ApiProperty(security: "is_granted('ROLE_EPIADMIN')", description: 'Status of the journal (enabled/disabled)')] // Property viewable and writable only by users with ROLE_ADMIN
    private int $status;


    #[ORM\Column(name: 'CREATION', type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE, nullable: false)]
    #[Groups(['read:Reviews'])]
    #[ApiProperty(security: "is_granted('ROLE_EPIADMIN')", description: 'Date the journal was created on the platform')]
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    private DateTimeInterface $creation;


    #[ORM\Column(name: 'PIWIKID', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    #[Groups(['read:Reviews'])]
    #[ApiProperty(security: "is_granted('ROLE_EPIADMIN')", description: 'Piwik (Matomo) analytics identifier of the journal')]
    private int $piwikid;


    #[ORM\OneToMany(mappedBy: 'review', targetEntity: Paper::class)]
    private Collection $papers;

    #[ORM\OneToMany(mappedBy: 'review', targetEntity: ReviewSetting::class, fetch: "EAGER")]
    private Collection $settings;

    #[ORM\ManyToMany(targetEntity: IndexingDatabase::class, mappedBy: 'reviews')]
    private Collection $indexingDatabases;

    public function __construct()
    {
        $this->papers = new ArrayCollection();
        $this->settings = new ArrayCollection();
        $this->indexingDatabases = new ArrayCollection();
    }

    public function getRvid(): ?int
    {
        return $this->rvid;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getSubtitle(): string
    {
        return $this->subtitle ?? "";
    }

    public function setSubtitle(?string $subtitle): self
    {
        $this->subtitle = $subtitle;

        return $this;
    }
    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function setStatus(int $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getCreation(): ?DateTimeInterface
    {
        return $this->creation;
    }

    public function setCreation(DateTimeInterface $creation): self
    {
        $this->creation = $creation;

        return $this;
    }

    public function getPiwikid(): ?int
    {
        return $this->piwikid;
    }

    public function setPiwikid(int $piwikid): self
    {
        $this->piwikid = $piwikid;

        return $this;
    }

    /**
     * @return \Doctrine\Common\Collections\Collection<int, \App\Entity\Paper>
     */
    public function getPapers(): Collection
    {
        return $this->papers;
    }

    public function addPaper(Paper $paper): self
    {
        if (!$this->papers->contains($paper)) {
            $this->papers[] = $paper;
            $paper->setReview($this);
        }

        return $this;
    }

    public function removePaper(Paper $paper): self
    {
        // Paper::review is non-nullable (RVID NOT NULL): a paper always keeps its review,
        // so removing it only detaches it from this collection without nulling the owning side.
        $this->papers->removeElement($paper);

        return $this;
    }

    /**
     * @return Collection<int, ReviewSetting>
     */
    public function getSettings(): Collection
    {
        return $this->settings;
    }

    public function addSetting(ReviewSetting $setting): self
    {
        if (!$this->settings->contains($setting)) {
            $this->settings->add($setting);
            $setting->setReview($this);
        }

        return $this;
    }

    public function removeSetting(ReviewSetting $setting): self
    {
        // set the owning side to null (unless already changed)
        if ($this->settings->removeElement($setting) && $setting->getReview() === $this) {
            $setting->setReview(null);
        }

        return $this;
    }

    public function getSetting(string $key): ?string
    {

        foreach ($this->settings->getValues() as $objectSetting){
            if($key === $objectSetting->getSetting()){
                return $objectSetting->getValue();
            }
        }

        return null;

    }

    /**
     * @return Collection<int, IndexingDatabase>
     */
    public function getIndexingDatabases(): Collection
    {
        return $this->indexingDatabases;
    }

    public function addIndexingDatabase(IndexingDatabase $indexingDatabase): self
    {
        if (!$this->indexingDatabases->contains($indexingDatabase)) {
            $this->indexingDatabases->add($indexingDatabase);
            $indexingDatabase->addReview($this);
        }
        return $this;
    }

    public function removeIndexingDatabase(IndexingDatabase $indexingDatabase): self
    {
        if ($this->indexingDatabases->removeElement($indexingDatabase)) {
            $indexingDatabase->removeReview($this);
        }
        return $this;
    }
}
