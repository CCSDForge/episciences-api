<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Parameter;
use App\AppConstants;
use App\Controller\BoardsController;
use App\Controller\FeedController;
use App\Resource\Boards;
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
                'groups' => ['read:Boards']
            ],
            read: false,
        ),
    ],
    controller: BoardsController::class,
    output: Boards::class

)]
#[ApiResource(
    uriTemplate: '/feed/rss/{code}',
    operations: [
        new GetCollection(
            openapi: new OpenApiOperation(
                tags: ['Feed'],
                summary: 'Feed RSS',
                description: 'Feed RSS',
            ),
            paginationEnabled: false,
            paginationClientEnabled: false,
            paginationClientItemsPerPage: false,
            normalizationContext: [
                'groups' => ['read:Feed']
            ],
            read: false,

        ),
    ],
    formats: ['xml'],
    controller: FeedController::class

)
]
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: self::URI_TEMPLATE . '{code}',
            openapi: new OpenApiOperation(
                tags: [OpenApiFactory::OAF_TAGS['review']],
                summary: 'Journal',
                description: 'Retrieves a Journal resource.',
                security: [['bearerAuth' => []],]
            ),

            normalizationContext: [
                'groups' => ['read:Review']
            ],

        #security: "is_granted('ROLE_SECRETARY')",
        ),

        new GetCollection(
            uriTemplate: self::URI_TEMPLATE,
            openapi: new OpenApiOperation(
                tags: [OpenApiFactory::OAF_TAGS['review']],
                summary: 'All Journals',
                description: 'Retrieves the collection of active journals',
                security: [['bearerAuth' => []],]
            ),
            normalizationContext: [
                'groups' => ['read:Reviews']
            ],

        //security: "is_granted('ROLE_EPIADMIN')",

        ),

        new Get(
            uriTemplate: AppConstants::APP_CONST['custom_operations']['uri_template'][AppConstants::STATS_DASHBOARD_ITEM],
            openapi: new OpenApiOperation(
                tags: [OpenApiFactory::OAF_TAGS['stats']],
                summary: "Dashboard",
                description: "",
                parameters: [
                    new Parameter(
                        name: AppConstants::YEAR_PARAM,
                        in: 'query',
                        description: 'The Year of submission',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: false,
                    ),
                    new Parameter(
                        name: AppConstants::START_AFTER_DATE,
                        in: 'query',
                        description: 'Start statistics after date [YYYY-MM-DD]: this parameter is ignored if the format is wrong',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: true,
                    ),
                    new Parameter(
                        name: AppConstants::WITH_DETAILS,
                        in: 'query',
                        description: 'More details',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: true,
                    )
                ]
            ),
            normalizationContext: [
                'groups' => [
                    AppConstants::APP_CONST['normalizationContext']['groups']['review']['item']['read'][0]
                ]

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
                description: "",
                parameters: [
                    new Parameter(
                        name: AppConstants::YEAR_PARAM,
                        in: 'query',
                        description: 'The Year of submission',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: false,
                    ),
                    new Parameter(
                        name: AppConstants::START_AFTER_DATE,
                        in: 'query',
                        description: 'Start statistics after date [YYYY-MM-DD]: this parameter is ignored if the format is wrong',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: true,
                    ),
                    new Parameter(
                        name: AppConstants::WITH_DETAILS,
                        in: 'query',
                        description: 'More details',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: true,
                    )
                ]
            ),
            normalizationContext: [
                'groups' => [
                    AppConstants::APP_CONST['normalizationContext']['groups']['review']['item']['read'][0]
                ]

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
                        description: 'The Year of submission',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: false,
                    ),
                    new Parameter(
                        name: AppConstants::START_AFTER_DATE,
                        in: 'query',
                        description: 'Start statistics after date [YYYY-MM-DD]: this parameter is ignored if the format is wrong',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: true,
                    ),
                    new Parameter(
                        name: AppConstants::WITH_DETAILS,
                        in: 'query',
                        description: 'More details',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: true,
                    )
                ]
            ),
            normalizationContext: [
                'groups' => [
                    AppConstants::APP_CONST['normalizationContext']['groups']['review']['item']['read'][0]
                ]

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
                        description: 'The Year of submission',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: false,
                    ),
                    new Parameter(
                        name: AppConstants::START_AFTER_DATE,
                        in: 'query',
                        description: 'Start statistics after date [YYYY-MM-DD]: this parameter is ignored if the format is wrong',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: true,
                    ),
                    new Parameter(
                        name: AppConstants::WITH_DETAILS,
                        in: 'query',
                        description: 'More details',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: true,
                    )
                ]
            ),
            normalizationContext: [
                'groups' => [
                    AppConstants::APP_CONST['normalizationContext']['groups']['review']['item']['read'][0]
                ]

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
                            'type' => 'datetime',

                        ]
                    ),
                ]
            ),
            normalizationContext: [
                'groups' => [
                    AppConstants::APP_CONST['normalizationContext']['groups']['review']['item']['read'][0]
                ]

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
class Review
{
    public const TABLE = 'REVIEW';
    public const PORTAL_ID = 0;
    public const STATUS_DISABLED = 0;
    public const STATUS_ENABLED = 1;
    public const URI_TEMPLATE = '/journals/';

    #[ORM\Column(name: 'RVID', type: 'integer', nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['collection']['read'][0],
            'read:Reviews', 'read:Review'
        ]
    )]
    #[ApiProperty(identifier: false)]
    private int $rvid;


    #[ORM\Column(name: 'CODE', type: 'string', length: 50, nullable: false)]
    #[groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['collection']['read'][0],
            'read:Reviews', 'read:Review', 'read:Browse:Authors'
        ]
    )]
    #[ApiProperty(identifier: true)]
    private string $code;


    #[ORM\Column(name: 'NAME', type: 'string', length: 2000, nullable: false)]
    #[Groups(['read:Reviews', 'read:Review'])]
    private string $name;


    #[ORM\Column(name: 'STATUS', type: 'smallint', nullable: false, options: ['unsigned' => true])]
    #[Groups(['read:Reviews'])]
    #[ApiProperty(security: "is_granted('ROLE_EPIADMIN')")] // Property viewable and writable only by users with ROLE_ADMIN
    private int $status;


    #[ORM\Column(name: 'CREATION', type: 'datetime', nullable: false)]
    #[Groups(['read:Reviews'])]
    #[ApiProperty(security: "is_granted('ROLE_EPIADMIN')")]
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    private DateTimeInterface $creation;


    #[ORM\Column(name: 'PIWIKID', type: 'integer', nullable: false, options: ['unsigned' => true])]
    #[Groups(['read:Reviews'])]
    #[ApiProperty(security: "is_granted('ROLE_EPIADMIN')")]
    private int $piwikid;


    #[ORM\OneToMany(mappedBy: 'review', targetEntity: Paper::class)]
    private Collection $papers;

    #[ORM\OneToMany(mappedBy: 'review', targetEntity: ReviewSetting::class, fetch: "EAGER")]
    #[Groups(
        [
            'read:Review',
            'read:Reviews'
        ]

    )]
    private Collection $settings;

    public function __construct()
    {
        $this->papers = new ArrayCollection();
        $this->settings = new ArrayCollection();
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
     * @return Collection
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
        // set the owning side to null (unless already changed)
        if ($this->papers->removeElement($paper) && $paper->getReview() === $this) {
            $paper->setReview();
        }

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
}
