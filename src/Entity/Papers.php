<?php
declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\AppConstants;
use App\Repository\PapersRepository;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Context;
use Symfony\Component\Serializer\Annotation\Groups;
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
            openapi: new OpenApiOperation(
                summary: 'Article',
                security: [['bearerAuth' =>  []],]
            ),

            normalizationContext: [
                'groups' => AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0]
            ],
            denormalizationContext: [
                'groups' => ['write:Paper']
            ]
        ),
        new GetCollection(
            openapi: new OpenApiOperation(
                summary: 'All Papers',
                security: [['bearerAuth' =>  []],]
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
#[ApiFilter(SearchFilter::class, properties: ['rvid' => 'exact', 'paperid' => 'exact', 'docid' => 'exact'])]
class Papers implements UserOwnedInterface
{
    public const TABLE = 'PAPERS';
    public const STATUS_SUBMITTED = 0;
    public const STATUS_OK_FOR_REVIEWING = 1; // reviewers have been assigned, but did not start their reports
    public const STATUS_BEING_REVIEWED = 2; // rating has begun (at least one reviewer has starter working on his rating report)
    public const STATUS_REVIEWED = 3; // rating is finished (all reviewers)
    public const STATUS_ACCEPTED = 4;
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

    public const ACCEPTED_SUBMISSIONS = [
        self::STATUS_ACCEPTED, // 4
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


    #[ORM\Column(name:'DOCID', type: 'integer', nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['collection']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['collection']['read'][0],
        ]
    )]

    private int $docid;


    #[ORM\Column(name: 'PAPERID', type: 'integer', nullable: true, options: ['unsigned' => true])]
    #[groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['collection']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['collection']['read'][0],
        ]
    )]
    private ?int $paperid;


    #[ORM\Column(name: 'DOI', type: 'string', length: 250, nullable: true )]
    #[groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['collection']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['collection']['read'][0],

        ]
    )]
    private ?string $doi;


    #[ORM\Column(name: 'RVID', type: 'integer', nullable: false, options: ['unsigned' => true])]
    #[groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['collection']['read'][0]
        ]
    )]
    private int $rvid;


    #[ORM\Column(name: 'VID', type: 'integer', nullable: false, options: ['unsigned' => true])]
    #[groups(
        AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0]
    )]
    private int $vid = 0;


    #[ORM\Column(name: 'SID', type: 'integer', nullable: false, options: ['unsigned' => true])]
    #[groups(
        AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0]
    )]
    private int $sid = 0;


    #[ORM\Column(name: 'UID', type: 'integer', nullable: false, options: ['unsigned' => true])]
    private int $uid;


    #[ORM\Column(name: 'STATUS', type: 'integer', nullable: false, options: ['unsigned' => true])]
    #[groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['collection']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['collection']['read'][0],
        ]
    )]
    private int $status = 0;


    #[ORM\Column(name:'IDENTIFIER', type: 'string', length: 500, nullable: false)]
    #[groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['collection']['read'][0]
        ])]
    private string $identifier;


    #[ORM\Column(name: 'VERSION', type: 'float', precision: 10, scale: 0, nullable: false, options: ['default' => 1])]
    #[groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
        ])]
    private $version = 1;


    #[ORM\Column(name: 'REPOID', type: 'integer', nullable: false, options: ['unsigned' => true])]
    #[groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
        ])]
    private int $repoid;


    #[ORM\Column(name: 'RECORD', type: 'text', length: 65535, nullable: false )]
    #[groups(
        [AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],]
    )]
    private string $record;
    #[ORM\Column(name: 'CONCEPT_IDENTIFIER', type: 'string', length: 500, nullable: true, options: ['comment' => 'This identifier represents all versions'])]
    private ?string $conceptIdentifier;

    #[ORM\Column(name: 'FLAG', type: 'string', length: 0, nullable: false, options: ['default' => 'submitted'])]
    #[groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
        ]
    )]
    #[ApiProperty(security: "is_granted('ROLE_EPIADMIN')")]
    private string $flag = 'submitted';


    #[ORM\Column(name: 'WHEN', type: 'datetime', nullable: false)]
    #[groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
        ]
    )]
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    private DateTime $when;


    #[ORM\Column(name: 'SUBMISSION_DATE', type: 'datetime', nullable: false)]
    #[groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
        ])]
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    private DateTime $submissionDate;


    #[ORM\Column(name: 'MODIFICATION_DATE', type: 'datetime', nullable: true)]
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    private ?DateTime $modificationDate;


    #[ORM\Column(name: 'PUBLICATION_DATE', type: 'datetime', nullable: true)]
    #[groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['collection']['read'][0]
        ])]
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    private ?DateTime $publicationDate;


    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'papers')]
    #[ORM\JoinColumn(name: 'UID', referencedColumnName: 'UID', nullable: false)]
    #[groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
        ])]
    #[ApiProperty(security: "is_granted('ROLE_SECRETARY')")]
    private UserInterface $user;


    #[ORM\ManyToOne(targetEntity: Review::class, inversedBy: 'papers')]
    #[ORM\JoinColumn(name: 'RVID', referencedColumnName: 'RVID', nullable: false)]
    #[groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['collection']['read'][0]
        ])]
    private Review $review;

    #[ORM\ManyToOne(targetEntity: Volume::class, inversedBy: 'papers')]
    #[ORM\JoinColumn(name: 'VID', referencedColumnName: 'VID', nullable: true)]
    private ?Volume $volume = null;

    public function __construct()
    {
        $this->when = new DateTime();
        $this->submissionDate = new DateTime();
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

    public function setStatus(int $status): self
    {
        $this->status = $status;

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
     * @return Papers
     */
    public function setFlag(string $flag): Papers
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

}