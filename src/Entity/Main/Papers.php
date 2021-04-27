<?php
declare(strict_types=1);
namespace App\Entity\Main;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use App\Filter\YearFilter;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use App\DataProvider\PapersStatsDataProvider;
use App\Resource\StatResource;

/**
 * Papers
 *
 *  @ApiResource(
 *     attributes={
 *          "normalization_context"={"groups"={"papers_read"}},
 *          "denormalization_context"={"groups"={"papers_write"}},
 *          "order"={"submissionDate":"DESC"},
 *     },
 *     paginationItemsPerPage=10,
 *     collectionOperations={
 *     "get",
 *      "get_stats_nb_submissions"={
 *            "method"="GET",
 *            "output"=StatResource::class,
 *            "path"="/papers/stats/nb-submissions",
 *            "dataProvider"=PapersStatsDataProvider::class,
 *        },
 *      "get_delay_between_submit_and_acceptance"={
 *            "method"="GET",
 *            "output"=StatResource::class,
 *            "path"="/papers/stats/delay-between-submit-and-acceptance",
 *            "dataProvider"=PapersStatsDataProvider::class,
 *        },
 *      "get_delay_between_submit_and_publication"={
 *            "method"="GET",
 *            "output"=StatResource::class,
 *            "path"="/papers/stats/delay-between-submit-and-publication",
 *            "dataProvider"=PapersStatsDataProvider::class,
 *        }
 *     },
 *     itemOperations={
 *     "get",
 *     }
 *     )
 * @ApiFilter(SearchFilter::class,
 *     properties={
 *     "rvid": "exact",
 *     "uid": "exact",
 *     "vid": "exact",
 *     "sid": "exact",
 *     "status": "exact",
 *     "repoid": "exact",
 *     },
 *
 *     )
 * @ORM\Table(name="PAPERS", indexes={@ORM\Index(name="FK_CONFID_idx", columns={"RVID"}), @ORM\Index(name="FK_REPOID_idx", columns={"REPOID"}), @ORM\Index(name="FK_VID_idx", columns={"VID"}), @ORM\Index(name="PAPERID", columns={"PAPERID"})})
 * @ORM\Entity(repositoryClass="App\Repository\Main\PapersRepository")
 */
class Papers
{
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

    public const ACCEPTED_SUBMISSIONS = [
        self::STATUS_ACCEPTED,
        self::STATUS_CE_WAITING_FOR_AUTHOR_SOURCES,
        self::STATUS_CE_AUTHOR_SOURCES_SUBMITTED,
        self::STATUS_CE_WAITING_AUTHOR_FINAL_VERSION,
        self::STATUS_CE_AUTHOR_FINAL_VERSION_SUBMITTED_WAITING_FOR_VALIDATION,
        self::STATUS_CE_REVIEW_FORMATTING_SUBMITTED,
        self::STATUS_CE_AUTHOR_FORMATTING_SUBMITTED_AND_VALIDATED,
        self::STATUS_CE_READY_TO_PUBLISH
    ];

    /**
     * @var int
     *
     * @ORM\Column(name="DOCID", type="integer", nullable=false, options={"unsigned"=true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     * @Groups({"papers_read"})
     *
     */
    private int $docid;

    /**
     * @var int|null
     *
     * @ORM\Column(name="PAPERID", type="integer", nullable=true, options={"unsigned"=true})
     * @Groups({"papers_read"})
     *
     */
    private ?int $paperid;

    /**
     * @var string|null
     *
     * @ORM\Column(name="DOI", type="string", length=250, nullable=true)
     * @Groups({"papers_read"})
     *
     */
    private ?string $doi;

    /**
     * @var int
     *
     * @ORM\Column(name="RVID", type="integer", nullable=false, options={"unsigned"=true})
     *@Groups({"papers_read"})
     */
    private int $rvid;

    /**
     * @var int
     *
     * @ORM\Column(name="VID", type="integer", nullable=false, options={"unsigned"=true})
     * @Groups({"papers_read"})
     *
     */
    private int $vid = 0;

    /**
     * @var int
     *
     * @ORM\Column(name="SID", type="integer", nullable=false, options={"unsigned"=true})
     * @Groups({"papers_read"})
     */
    private int $sid = 0;

    /**
     * @var int
     *
     * @ORM\Column(name="UID", type="integer", nullable=false, options={"unsigned"=true})
     */
    private int $uid;

    /**
     * @var int
     *
     * @ORM\Column(name="STATUS", type="integer", nullable=false, options={"unsigned"=true})
     * @Groups({"papers_read"})
     *
     */
    private int $status = 0;

    /**
     * @var string
     *
     * @ORM\Column(name="IDENTIFIER", type="string", length=500, nullable=false)
     * @Groups({"papers_read"})
     *
     */
    private string $identifier;

    /**
     * @var float
     *
     * @ORM\Column(name="VERSION", type="float", precision=10, scale=0, nullable=false, options={"default"="1"})
     * @Groups({"papers_read"})
     *
     */
    private $version = 1;

    /**
     * @var int
     *
     * @ORM\Column(name="REPOID", type="integer", nullable=false, options={"unsigned"=true})
     * @Groups({"papers_read"})
     *
     */
    private int $repoid;

    /**
     * @var string
     *
     * @ORM\Column(name="RECORD", type="text", length=65535, nullable=false)
     */
    private string $record;

    /**
     * @var string|null
     *
     * @ORM\Column(name="DESCRIPTION", type="text", length=65535, nullable=true)
     */
    private ?string $description;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="WHEN", type="datetime", nullable=false)
     */
    private DateTime $when;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="SUBMISSION_DATE", type="datetime", nullable=false)
     *
     * @ApiFilter(YearFilter::class)
     * @Groups({"papers_read"})
     */
    private DateTime $submissionDate;

    /**
     * @var DateTime|null
     *
     * @ORM\Column(name="MODIFICATION_DATE", type="datetime", nullable=true)
     */
    private ?DateTime $modificationDate;

    /**
     * @var DateTime|null
     *
     * @ORM\Column(name="PUBLICATION_DATE", type="datetime", nullable=true)
     * @Groups({"papers_read"})
     *
     */
    private ?DateTime $publicationDate;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="papers")
     * @ORM\JoinColumn(name="UID", referencedColumnName="UID", nullable=false)
     * @Groups({"papers_read"})
     */
    private UserInterface $author;

    /**
     * @ORM\ManyToOne(targetEntity=Review::class, inversedBy="papers")
     * @ORM\JoinColumn(name="RVID", referencedColumnName="RVID", nullable=false)
     *
     */
    private Review $review;

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

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

    public function getAuthor(): ?UserInterface
    {
        return $this->author;
    }

    public function setAuthor(UserInterface $author): self
    {
        $this->author = $author;

        return $this;
    }

    public function getReview(): ?Review
    {
        return $this->review;
    }

    public function setReview(Review $review): self
    {
        $this->review = $review;

        return $this;
    }

}
