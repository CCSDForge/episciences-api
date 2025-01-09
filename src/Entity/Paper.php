<?php
declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Parameter;
use App\AppConstants;
use App\OpenApi\OpenApiFactory;
use App\Repository\PapersRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;


#[ORM\Table(name: self::TABLE)]
#[ORM\Index(columns: ['REPOID'], name: 'FK_REPOID_idx')]
#[ORM\Index(columns: ['VID'], name: 'FK_VID_idx')]
#[ORM\Index(columns: ['RVID'], name: 'FK_RVID_idx')]
#[ORM\Index(columns: ['STATUS'], name: 'STATUS')]
#[ORM\Index(columns: ['PAPERID'], name: 'PAPERID')]
#[ORM\Index(columns: ['SID'], name: 'SID')]
#[ORM\Index(columns: ['UID'], name: 'UID')]
#[ORM\Index(columns: ['SUBMISSION_DATE'], name: 'SUBMISSION_DATE')]
#[ORM\Index(columns: ['PUBLICATION_DATE'], name: 'PUBLICATION_DATE')]
#[ORM\Index(columns: ['FLAG'], name: 'FLAG')]
#[ORM\Index(columns: ['RECORD'], name: 'RECORD')]
#[ORM\Entity(repositoryClass: PapersRepository::class)]
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: self::URI_TEMPLATE . '{docid}',
            openapi: new OpenApiOperation(
                tags: [OpenApiFactory::OAF_TAGS['paper']],
                summary: 'The paper identified by docid or paperid',
                security: [['bearerAuth' => []],]
            ),

            normalizationContext: [
                'groups' => AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0]
            ],
            denormalizationContext: [
                'groups' => ['write:Paper']
            ]
        ),
        new GetCollection(
            uriTemplate: self::URI_TEMPLATE,
            openapi: new OpenApiOperation(
                tags: [OpenApiFactory::OAF_TAGS['paper']],
                summary: 'All Papers (In offline mode: only published papers )',
                parameters: [
                    new Parameter(
                        name: 'rvcode',
                        in: 'query',
                        description: 'Journal Code (ex. epijinfo)',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: false,
                        schema: [
                            'type' => 'string',
                        ],
                        explode: false,
                    ),
                    new Parameter(
                        name: 'only_accepted',
                        in: 'query',
                        description: 'If this is true, only accepted documents will be returned.',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: true,
                        schema: [
                            'type' => 'boolean',
                        ]
                    ),
                    new Parameter(
                        name: 'type',
                        in: 'query',
                        description: 'Paper type',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: false,
                        schema: [
                            'type' => 'string',
                        ],
                        explode: false
                    ),
                    new Parameter(
                        name: 'type[]',
                        in: 'query',
                        description: 'Paper types',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: false,
                        schema: [
                            'type' => 'array',
                            'items' => [
                                'type' => 'string'
                            ]
                        ],
                        explode: true
                    ),
                    new Parameter(
                        name: AppConstants::YEAR_PARAM,
                        in: 'query',
                        description: 'The Year of publication',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: false,
                        schema: [
                            'type' => 'integer',
                        ],explode: false,
                        allowReserved: false
                    ),
                    new Parameter(
                        name: 'year[]',
                        in: 'query',
                        description: 'The Year of publication',
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

                ],
                security: [['bearerAuth' => []],]
            ),
            normalizationContext: [
                'groups' => ['read:Papers']
            ],
            denormalizationContext: [
                'groups' => ['write:Papers']
            ]
        ),


    ],
    order: ['rvid' => 'DESC'],

)]
#[ApiFilter(SearchFilter::class, properties: self::FILTERS)]
#[ApiFilter(DateFilter::class, properties: ['submissionDate'])]
#[ApiFilter(DateFilter::class, properties: ['publicationDate'])]
class Paper implements UserOwnedInterface
{
    public const COLLECTION_NAME = '_api_/papers/_get_collection';
    public const FILTERS = [
        'rvid' => AppConstants::FILTER_TYPE_EXACT,
        'doi' => AppConstants::FILTER_TYPE_EXACT,
        'paperid' => AppConstants::FILTER_TYPE_EXACT,
        'docid' => AppConstants::FILTER_TYPE_EXACT,
        'vid' => AppConstants::FILTER_TYPE_EXACT,
        'sid' => AppConstants::FILTER_TYPE_EXACT,
        'repoid' => AppConstants::FILTER_TYPE_EXACT,
        'flag' => AppConstants::FILTER_TYPE_EXACT,
        'status' => AppConstants::FILTER_TYPE_EXACT,
    ];

    public const TABLE = 'PAPERS';
    public const URI_TEMPLATE = '/papers/';
    public const STATUS_SUBMITTED = 0;
    public const STATUS_OK_FOR_REVIEWING = 1; // reviewers have been assigned, but did not start their reports
    public const STATUS_BEING_REVIEWED = 2; // rating has begun (at least one reviewer has starter working on his rating report)
    public const STATUS_REVIEWED = 3; // rating is finished (all reviewers)
    public const STATUS_STRICTLY_ACCEPTED = 4;
    public const STATUS_REFUSED = 5;
    public const STATUS_OBSOLETE = 6;
    public const STATUS_WAITING_FOR_MINOR_REVISION = 7;
    public const STATUS_WAITING_FOR_MAJOR_REVISION = 15;
    public const STATUS_TMP_VERSION = 9;
    public const STATUS_NO_REVISION = 10;
    public const STATUS_NEW_VERSION = 11;
    public const STATUS_WAITING_FOR_COMMENTS = 8;
    public const STATUS_DELETED = 12; //paper removed by contributor (before publication)
    public const STATUS_REMOVED = 13; // paper removed by editorial board (after publication)
    public const STATUS_REVIEWERS_INVITED = 14; // reviewers have been invited, but no one has accepted yet
    public const STATUS_PUBLISHED = 16;
    public const STATUS_ABANDONED = 17;
    //Copy editing
    public const STATUS_CE_WAITING_FOR_AUTHOR_SOURCES = 18;
    public const STATUS_CE_AUTHOR_SOURCES_SUBMITTED = 19;
    public const STATUS_CE_REVIEW_FORMATTING_SUBMITTED = 20;
    public const STATUS_CE_WAITING_AUTHOR_FINAL_VERSION = 21;
    public const STATUS_CE_AUTHOR_FINAL_VERSION_SUBMITTED_WAITING_FOR_VALIDATION = 22;
    public const STATUS_CE_READY_TO_PUBLISH = 23;
    public const STATUS_CE_AUTHOR_FORMATTING_SUBMITTED_AND_VALIDATED = 24;
    public const STATUS_ACCEPTED_WAITING_FOR_AUTHOR_FINAL_VERSION = 26;
    public const STATUS_ACCEPTED_WAITING_FOR_MAJOR_REVISION = 27;
    public const STATUS_TMP_VERSION_ACCEPTED = 25;
    public const STATUS_ACCEPTED_FINAL_VERSION_SUBMITTED_WAITING_FOR_COPY_EDITORS_FORMATTING = 28;
    public const STATUS_TMP_VERSION_ACCEPTED_AFTER_AUTHOR_MODIFICATION = 29;
    public const STATUS_TMP_VERSION_ACCEPTED_WAITING_FOR_MINOR_REVISION = 30;
    public const STATUS_TMP_VERSION_ACCEPTED_WAITING_FOR_MAJOR_REVISION = 31;
    public const STATUS_ACCEPTED_WAITING_FOR_AUTHOR_VALIDATION = 32;
    public const STATUS_APPROVED_BY_AUTHOR_WAITING_FOR_FINAL_PUBLICATION = 33;
    public const PAPERS_GROUPS = [
        AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
        AppConstants::APP_CONST['normalizationContext']['groups']['papers']['collection']['read'][0],
        AppConstants::APP_CONST['normalizationContext']['groups']['volume']['item']['read'][0],
        AppConstants::APP_CONST['normalizationContext']['groups']['volume']['collection']['read'][0],
        AppConstants::APP_CONST['normalizationContext']['groups']['section']['item']['read'][0],
        AppConstants::APP_CONST['normalizationContext']['groups']['section']['collection']['read'][0]
    ];

    public const STATUS_ACCEPTED = [
        self::STATUS_STRICTLY_ACCEPTED, // 4
        self::STATUS_CE_WAITING_FOR_AUTHOR_SOURCES, // 18
        self::STATUS_CE_AUTHOR_SOURCES_SUBMITTED, // 19
        self::STATUS_CE_WAITING_AUTHOR_FINAL_VERSION, // 21
        self::STATUS_CE_AUTHOR_FINAL_VERSION_SUBMITTED_WAITING_FOR_VALIDATION, // 22
        self::STATUS_CE_REVIEW_FORMATTING_SUBMITTED, // 20
        self::STATUS_CE_AUTHOR_FORMATTING_SUBMITTED_AND_VALIDATED, //24
        self::STATUS_CE_READY_TO_PUBLISH,//23,
        self::STATUS_ACCEPTED_WAITING_FOR_AUTHOR_FINAL_VERSION, //26
        self::STATUS_ACCEPTED_WAITING_FOR_MAJOR_REVISION, //27
        self::STATUS_TMP_VERSION_ACCEPTED, //25
        self::STATUS_ACCEPTED_FINAL_VERSION_SUBMITTED_WAITING_FOR_COPY_EDITORS_FORMATTING, //28
        self::STATUS_TMP_VERSION_ACCEPTED_AFTER_AUTHOR_MODIFICATION, //29
        self::STATUS_TMP_VERSION_ACCEPTED_WAITING_FOR_MINOR_REVISION, //30
        self::STATUS_TMP_VERSION_ACCEPTED_WAITING_FOR_MAJOR_REVISION //31
    ];


    public const STATUS_DICTIONARY = [
        self::STATUS_SUBMITTED => 'submitted',
        self::STATUS_OK_FOR_REVIEWING => 'waitingForReviewing',
        self::STATUS_BEING_REVIEWED => 'underReview',
        self::STATUS_REVIEWED => 'reviewed pending editorial decision',
        self::STATUS_STRICTLY_ACCEPTED => 'strictly_accepted',
        self::STATUS_PUBLISHED => 'published',
        self::STATUS_REFUSED => 'refused',
        self::STATUS_OBSOLETE => 'obsolete',
        self::STATUS_WAITING_FOR_MINOR_REVISION => 'pendingMinorRevision',
        self::STATUS_WAITING_FOR_MAJOR_REVISION => 'pendingMajorRevision',
        self::STATUS_WAITING_FOR_COMMENTS => 'pendingClarification',
        self::STATUS_TMP_VERSION => 'temporaryVersion',
        self::STATUS_NO_REVISION => 'revisionRequestAnswerWithoutAnyModifications',
        self::STATUS_NEW_VERSION => 'answerToRevisionRequestNewVersion',
        self::STATUS_DELETED => 'deleted',
        self::STATUS_ABANDONED => 'abandoned',
        self::STATUS_CE_WAITING_FOR_AUTHOR_SOURCES => "waitingForAuthorsSources",
        self::STATUS_CE_AUTHOR_SOURCES_SUBMITTED => 'waitingForFormattingByTheJournal',
        self::STATUS_CE_WAITING_AUTHOR_FINAL_VERSION => "waitingForAuthorsFinalVersion",
        self::STATUS_CE_AUTHOR_FINAL_VERSION_SUBMITTED_WAITING_FOR_VALIDATION =>
            'finalVersionSubmittedWaitingForValidation',
        self::STATUS_CE_REVIEW_FORMATTING_SUBMITTED => 'formattingByJournalCompletedWaitingForAFinalVersion',
        self::STATUS_CE_AUTHOR_FORMATTING_SUBMITTED_AND_VALIDATED => "formattingByAuthorCompletedWaitingForFinalVersion",
        self::STATUS_CE_READY_TO_PUBLISH => 'readyToPublish',
        self::STATUS_TMP_VERSION_ACCEPTED => "acceptedTemporaryVersionWaitingForAuthorsFinalVersion",
        self::STATUS_ACCEPTED_WAITING_FOR_AUTHOR_FINAL_VERSION => "acceptedWaitingForAuthorsFinalVersion",
        self::STATUS_ACCEPTED_WAITING_FOR_MAJOR_REVISION => 'acceptedWaitingForMajorRevision',
        self::STATUS_ACCEPTED_FINAL_VERSION_SUBMITTED_WAITING_FOR_COPY_EDITORS_FORMATTING =>
            'acceptedFinalVersionSubmittedWaitingForFormattingByCopyEditors',
        self::STATUS_TMP_VERSION_ACCEPTED_AFTER_AUTHOR_MODIFICATION =>
            "acceptedTemporaryVersionAfterAuthorsModifications",
        self::STATUS_TMP_VERSION_ACCEPTED_WAITING_FOR_MINOR_REVISION =>
            'acceptedTemporaryVersionWaitingForMinorRevision',
        self::STATUS_TMP_VERSION_ACCEPTED_WAITING_FOR_MAJOR_REVISION =>
            'acceptedTemporaryVersionWaitingForMajorRevision"',
        self::STATUS_ACCEPTED_WAITING_FOR_AUTHOR_VALIDATION => "AcceptedWaitingForAuthorsValidation",
        self::STATUS_APPROVED_BY_AUTHOR_WAITING_FOR_FINAL_PUBLICATION => "'AcceptedWaitingForFinalPublication'",
        self::STATUS_REMOVED => 'deletedByTheJournal',

    ];


    #[ORM\Column(name: 'DOCID', type: 'integer', nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    //#[ApiProperty(security: "is_granted('papers_manage', object)")]
    #[groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['collection']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0]
        ]
    )]
    private int $docid;


    #[ORM\Column(name: 'PAPERID', type: 'integer', nullable: true, options: ['unsigned' => true])]
    #[groups(self::PAPERS_GROUPS)]
    private ?int $paperid;

    #[ORM\Column(name: 'TYPE', type: 'json', nullable: true)]
    private array $type;

    #[ORM\Column(name: 'DOI', type: 'string', length: 250, nullable: true)]
    #[groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['collection']['read'][0],
        ]
    )]
    private ?string $doi;


    #[ORM\Column(name: 'RVID', type: 'integer', nullable: false, options: ['unsigned' => true])]
    private int $rvid;


    #[ORM\Column(name: 'VID', type: 'integer', nullable: false, options: ['unsigned' => true])]
    private int $vid = 0;


    #[ORM\Column(name: 'SID', type: 'integer', nullable: false, options: ['unsigned' => true])]
    private int $sid = 0;


    #[ORM\Column(name: 'UID', type: 'integer', nullable: false, options: ['unsigned' => true])]
    private int $uid;


    #[ORM\Column(name: 'STATUS', type: 'integer', nullable: false, options: ['unsigned' => true])]
    #[groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['collection']['read'][0],
        ]
    )]
    private int $status = self::STATUS_SUBMITTED;

    #[groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['collection']['read'][0],
        ]
    )]
    private string $statusLabel = self::STATUS_DICTIONARY[self::STATUS_SUBMITTED];

    #[ORM\Column(name: 'IDENTIFIER', type: 'string', length: 500, nullable: false)]
    private string $identifier;


    #[ORM\Column(name: 'VERSION', type: 'float', precision: 10, scale: 0, nullable: false, options: ['default' => 1])]
    private $version = 1;


    #[ORM\Column(name: 'REPOID', type: 'integer', nullable: false, options: ['unsigned' => true])]
    private int $repoid;


    #[ORM\Column(name: 'RECORD', type: 'text', length: 65535, nullable: false)]
    private string $record;
    #[ORM\Column(name: 'DOCUMENT', type: 'json', nullable: false)]
    //#[groups(self::PAPERS_GROUPS)]

    #[groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
        ]
    )]
    private array $document;
    #[ORM\Column(name: 'CONCEPT_IDENTIFIER', type: 'string', length: 500, nullable: true, options: ['comment' => 'This identifier represents all versions'])]
    private ?string $conceptIdentifier;

    #[ORM\Column(name: 'FLAG', type: 'string', length: 0, nullable: false, options: ['default' => 'submitted'])]
    #[ApiProperty(security: "is_granted('papers_manage', object)")]
    private string $flag = 'submitted';


    #[ORM\Column(name: 'WHEN', type: 'datetime', nullable: false)]
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    private DateTime $when;


    #[ORM\Column(name: 'SUBMISSION_DATE', type: 'datetime', nullable: false)]
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    private DateTime $submissionDate;


    #[ORM\Column(name: 'MODIFICATION_DATE', type: 'datetime', nullable: true)]
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    private ?DateTime $modificationDate;


    #[ORM\Column(name: 'PUBLICATION_DATE', type: 'datetime', nullable: true)]
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    private ?DateTime $publicationDate;


    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'papers')]
    #[ORM\JoinColumn(name: 'UID', referencedColumnName: 'UID', nullable: false)]
    #[groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
        ])]
    #[ApiProperty(security: "is_granted('papers_manage', object)")]
    private UserInterface $user;


    #[ORM\ManyToOne(targetEntity: Review::class, inversedBy: 'papers')]
    #[ORM\JoinColumn(name: 'RVID', referencedColumnName: 'RVID', nullable: false)]
    #[groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['collection']['read'][0],
        ]
    )]
    private Review $review;

    #[ORM\ManyToOne(targetEntity: Section::class, inversedBy: 'papers')]
    #[ORM\JoinColumn(name: 'SID', referencedColumnName: 'SID', nullable: true)]
    private ?Section $section = null;

    #[ORM\ManyToOne(targetEntity: Volume::class, inversedBy: 'papers')]
    #[ORM\JoinColumn(name: 'VID', referencedColumnName: 'VID', nullable: true)]
    private ?Volume $volume = null;

    #[ORM\OneToMany(mappedBy: 'papers', targetEntity: UserAssignment::class)]
    #[ApiProperty(security: "is_granted('papers_manage', object)")]
    private Collection $assignments;
    #[groups([
        AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
    ])]
    #[ApiProperty(security: "is_granted('papers_manage', object)")]
    private array $editors = [];

    #[groups([
        AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
    ])]
    #[ApiProperty(security: "is_granted('papers_manage', object)")]
    private array $reviewers = [];
    #[groups([
        AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
    ])]
    #[ApiProperty(security: "is_granted('papers_manage', object)")]
    private array $copyEditors = [];
    #[groups([
        AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
    ])]
    #[ApiProperty(security: "is_granted('papers_manage', object)")]
    private array $coAuthors = [];

    #[ORM\OneToMany(mappedBy: 'papers', targetEntity: PaperConflicts::class)]
    #[groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
        ]
    )]
    #[ApiProperty(security: "is_granted('papers_manage', object)")]
    private Collection $conflicts;

    /**
     * @var Collection<int, PaperComment>
     */
    #[ORM\OneToMany(mappedBy: 'paper', targetEntity: PaperComment::class, orphanRemoval: true)]
    #[ApiProperty(security: "is_granted('papers_manage', object)")]
    #[groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
        ]
    )]
    #[ORM\OrderBy(['when' => 'DESC'])]
    private Collection $comments;

    public function __construct()
    {
        $this->when = new DateTime();
        $this->submissionDate = new DateTime();
        $this->assignments = new ArrayCollection();
        $this->conflicts = new ArrayCollection();
        $this->comments = new ArrayCollection();
    }


    public function getDocid(): ?int
    {
        return $this->docid;
    }

    public function getPaperid(): ?int
    {
        return $this->paperid;
    }

    public function setPaperid(?int $paperid): self
    {
        $this->paperid = $paperid;

        return $this;
    }

    public function getDoi(): ?string
    {
        return $this->doi;
    }

    public function setDoi(?string $doi): self
    {
        $this->doi = $doi;

        return $this;
    }

    public function getRvid(): ?int
    {
        return $this->rvid;
    }

    public function setRvid(int $rvid): self
    {
        $this->rvid = $rvid;

        return $this;
    }

    public function getVid(): ?int
    {
        return $this->vid;
    }

    public function setVid(int $vid): self
    {
        $this->vid = $vid;

        return $this;
    }

    public function getSid(): ?int
    {
        return $this->sid;
    }

    public function setSid(int $sid): self
    {
        $this->sid = $sid;

        return $this;
    }

    public function getUid(): ?int
    {
        return $this->uid;
    }

    public function setUid(int $uid): self
    {
        $this->uid = $uid;

        return $this;
    }

    public function getStatus(): ?int
    {
        return $this->status;
    }

    /**
     * @return string
     */
    public function getStatusLabel(): string
    {
        return $this->statusLabel;
    }


    public function setStatus(int $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function setStatusLabel(string $statusLabel = self::STATUS_DICTIONARY[self::STATUS_SUBMITTED]): self
    {
        $this->statusLabel = $statusLabel;
        return $this;
    }

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): self
    {
        $this->identifier = $identifier;

        return $this;
    }

    public function getVersion(): ?float
    {
        return $this->version;
    }

    public function setVersion(float $version): self
    {
        $this->version = $version;

        return $this;
    }

    public function getRepoid(): ?int
    {
        return $this->repoid;
    }

    public function setRepoid(int $repoid): self
    {
        $this->repoid = $repoid;

        return $this;
    }

    public function getRecord(): ?string
    {

        return $this->record;
    }

    public function setRecord(string $record): self
    {
        $this->record = $record;

        return $this;
    }

    public function getConceptIdentifier(): ?string
    {
        return $this->conceptIdentifier;
    }

    public function setDescription(?string $conceptIdentifier): self
    {
        $this->conceptIdentifier = $conceptIdentifier;

        return $this;
    }

    public function getWhen(): ?DateTime
    {
        return $this->when;
    }

    public function setWhen(DateTime $when): self
    {
        $this->when = $when;

        return $this;
    }

    public function getSubmissionDate(): ?DateTime
    {
        return $this->submissionDate;
    }

    public function setSubmissionDate(DateTime $submissionDate): self
    {
        $this->submissionDate = $submissionDate;

        return $this;
    }

    public function getModificationDate(): ?DateTime
    {
        return $this->modificationDate;
    }

    public function setModificationDate(?DateTime $modificationDate): self
    {
        $this->modificationDate = $modificationDate;

        return $this;
    }

    public function getPublicationDate(): ?DateTime
    {
        return $this->publicationDate;
    }

    public function setPublicationDate(?DateTime $publicationDate): self
    {
        $this->publicationDate = $publicationDate;

        return $this;
    }

    public function getUser(): ?UserInterface
    {
        return $this->user;
    }

    public function setUser(UserInterface $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getReview(): ?Review
    {
        return $this->review;
    }

    public function setReview(Review $review = null): self
    {
        $this->review = $review;

        return $this;
    }

    /**
     * @return string
     */
    public function getFlag(): string
    {
        return $this->flag;
    }

    /**
     * @param string $flag
     * @return Paper
     */
    public function setFlag(string $flag): Paper
    {
        $this->flag = $flag;
        return $this;
    }

    public function getVolume(): ?Volume
    {
        return $this->volume;
    }

    public function setVolume(?Volume $volume): self
    {
        $this->volume = $volume;

        return $this;
    }

    public function getSection(): ?Section
    {
        return $this->section;
    }

    public function setSection(?Section $section): self
    {
        $this->section = $section;

        return $this;
    }

    /**
     * @return Collection<int, UserAssignment>
     */
    public function getAssignments(): Collection
    {
        return $this->assignments;
    }

    public function addAssignment(UserAssignment $assignment): self
    {
        if (!$this->assignments->contains($assignment)) {
            $this->assignments->add($assignment);
            $assignment->setPapers($this);
        }

        return $this;
    }

    public function removeAssignment(UserAssignment $assignment): self
    {
        // set the owning side to null (unless already changed)
        if ($this->assignments->removeElement($assignment) && $assignment->getPapers() === $this) {
            $assignment->setPapers(null);
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getEditors(): array
    {

        if (empty($this->editors)) {
            $this->assignmentsProcess();
        }

        return $this->editors;
    }

    /**
     * @param array $editors
     * @return Paper
     */
    public function setEditors(array $editors): self
    {

        $this->editors = $editors;
        return $this;
    }

    /**
     * @return array
     */
    public function getReviewers(): array
    {
        if (empty($this->reviewers)) {
            $this->assignmentsProcess();
        }

        return $this->reviewers;
    }

    /**
     * @param array $reviewers
     * @return Paper
     */
    public function setReviewers(array $reviewers): self
    {
        $this->reviewers = $reviewers;
        return $this;
    }


    private function assignmentsProcess(): void
    {
        $editors = [];
        $reviewers = [];
        $coAuthors = [];
        $copyEditors = [];

        /** @var UserAssignment $assignment */

        foreach ($this->assignments as $assignment) {

            if ($assignment->getRoleid() === UserAssignment::ROLE_EDITOR) {
                $editors[$assignment->getUid()][] = $assignment;
            } elseif ($assignment->getRoleid() === UserAssignment::ROLE_COPY_EDITOR) {
                $copyEditors[$assignment->getUid()][] = $assignment;

            } elseif ($assignment->getRoleid() === UserAssignment::ROLE_REVIEWER) {
                $reviewers[$assignment->getUid()][] = $assignment;

            } elseif ($assignment->getRoleid() === UserAssignment::ROLE_CO_AUTHOR) {
                $coAuthors[$assignment->getUid()][] = $assignment;
            }

        }

        $this
            ->setEditors($editors)
            ->setReviewers($reviewers)
            ->setCopyEditors($copyEditors)
            ->setCoAuthors($coAuthors);
    }

    /**
     * @return array
     */
    public function getCopyEditors(): array
    {
        if (empty($this->copyEditors)) {
            $this->assignmentsProcess();
        }

        return $this->copyEditors;
    }

    /**
     * @param array $copyEditors
     * @return Paper
     */
    public function setCopyEditors(array $copyEditors): self
    {
        $this->copyEditors = $copyEditors;
        return $this;
    }

    /**
     * @return array
     */
    public function getCoAuthors(): array
    {
        if (empty($this->coAuthors)) {
            $this->assignmentsProcess();
        }

        return $this->coAuthors;
    }

    /**
     * @param array $coAuthors
     * @return Paper
     */
    public function setCoAuthors(array $coAuthors): self
    {
        $this->coAuthors = $coAuthors;
        return $this;
    }

    /**
     * @return Collection<int, PaperConflicts>
     */
    public function getConflicts(): Collection
    {

        $this->conflictsProcess();
        return $this->conflicts;
    }

    public function addConflict(PaperConflicts $conflict): self
    {
        if (!$this->conflicts->contains($conflict)) {
            $this->conflicts->add($conflict);
            $conflict->setPapers($this);
        }

        return $this;
    }

    public function removeConflict(PaperConflicts $conflict): self
    {
        // set the owning side to null (unless already changed)
        if ($this->conflicts->removeElement($conflict) && $conflict->getPapers() === $this) {
            $conflict->setPapers(null);
        }

        return $this;
    }


    private function conflictsProcess(): void
    {
        $conflicts = [];

        /** @var PaperConflicts $conflict */

        foreach ($this->conflicts as $conflict) {

            if($conflict instanceof PaperConflicts){
                $conflicts[$conflict->getAnswer()][$conflict->getBy()] = $conflict;
            }
        }

        if(!empty($conflicts)){
            $this->conflicts = new ArrayCollection($conflicts);
        }


    }

    public function getStatusDictionaryLabel(): string
    {
        return self::STATUS_DICTIONARY[$this->getStatus()] ?? 'status_label_not_found';
    }

    /**
     * users keys
     * @return array [101010, .... ]
     */
    final public function getUsersAllowedToEditPaperCitations(): array
    {
        return array_merge(
            [$this->getUid()],
            array_keys($this->getCoAuthors()),
            array_keys($this->getEditors()),
            array_keys($this->getCopyEditors())
        );

    }

    public function getDocument(): array
    {
        return $this->document;
    }

    public function setDocument(array $document): self
    {
        $this->document = $document;
        return $this;
    }


    public function getType(): ?array
    {
        return $this->type;
    }


    public function setType(array $type = null): self
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return Collection<int, PaperComment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(PaperComment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setPaper($this);
        }

        return $this;
    }

    public function removeComment(PaperComment $comment): static
    {
        // set the owning side to null (unless already changed)
        if ($this->comments->removeElement($comment) && $comment->getPaper() === $this) {
            $comment->setPaper($this);
        }

        return $this;
    }

}
