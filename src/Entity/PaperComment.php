<?php

namespace App\Entity;
use App\AppConstants;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use App\Repository\PaperCommentRepository;

/**
 * PaperComments
 */
#[ORM\Table(name: 'PAPER_COMMENTS')]
#[ORM\Index(name: 'DOCID', columns: ['DOCID'])]
#[ORM\Index(name: 'TYPE', columns: ['TYPE'])]
#[ORM\Index(name: 'UID', columns: ['UID'])]
#[ORM\Index(name: 'DEADLINE', columns: ['DEADLINE'])]
#[ORM\Index(name: 'WHEN', columns: ['WHEN'])]
#[ORM\Index(name: 'PARENTID', columns: ['PARENTID'])]
#[ORM\Entity(repositoryClass: PaperCommentRepository::class)]


class PaperComment
{
    public const TABLE = 'PAPER_COMMENTS';


    public const TYPE_INFO_REQUEST = 0;
    public const TYPE_INFO_ANSWER = 1;
    public const TYPE_REVISION_REQUEST = 2;
    public const TYPE_REVISION_ANSWER_COMMENT = 3;

    public const TYPE_AUTHOR_COMMENT = 4;
    #git #320
    public const TYPE_REVISION_CONTACT_COMMENT = 5;
    public const TYPE_REVISION_ANSWER_TMP_VERSION = 6;
    public const TYPE_REVISION_ANSWER_NEW_VERSION = 7;
    public const TYPE_SUGGESTION_ACCEPTATION = 8;
    public const TYPE_SUGGESTION_REFUS = 9;
    public const TYPE_SUGGESTION_NEW_VERSION = 10;
    public const TYPE_CONTRIBUTOR_TO_REVIEWER = 11;
    public const TYPE_EDITOR_COMMENT = 12;

    public const TYPE_EDITOR_MONITORING_REFUSED = 13;
    public const TYPE_AUTHOR_SOURCES_DEPOSED_ANSWER = 14;
    public const TYPE_WAITING_FOR_AUTHOR_FORMATTING_REQUEST = 15;
    public const TYPE_AUTHOR_FORMATTING_ANSWER = 16;

    public const TYPE_AUTHOR_FORMATTING_VALIDATED_REQUEST = 17;
    public const TYPE_REVIEW_FORMATTING_DEPOSED_REQUEST = 18;
    public const TYPE_CE_AUTHOR_FINAL_VERSION_SUBMITTED = 19;
    public const TYPE_WAITING_FOR_AUTHOR_SOURCES_REQUEST = 20;
    public const TYPE_ACCEPTED_ASK_AUTHOR_VALIDATION = 21;
    public const TYPE_LABEL = [
        self::TYPE_INFO_REQUEST => "request_for_clarification",
        self::TYPE_INFO_ANSWER => "response_for_clarification",
        self::TYPE_REVISION_REQUEST => "revision_request",
        self::TYPE_REVISION_ANSWER_COMMENT => "revision_request_response_comment",
        self::TYPE_AUTHOR_COMMENT => "cover_letter",
        self::TYPE_REVISION_CONTACT_COMMENT => 'revision_request',
        self::TYPE_REVISION_ANSWER_TMP_VERSION => "revision_request_response_temporary_version",
        self::TYPE_REVISION_ANSWER_NEW_VERSION => "revision_request_response_new_version",
        self::TYPE_CONTRIBUTOR_TO_REVIEWER => 'response_to_request_for_clarification',
        self::TYPE_SUGGESTION_ACCEPTATION => "acceptance_suggestion",
        self::TYPE_SUGGESTION_REFUS => "refusal_suggestion",
        self::TYPE_SUGGESTION_NEW_VERSION => "revision_suggestion",
        self::TYPE_EDITOR_MONITORING_REFUSED => "refusal_to_follow_up",
        self::TYPE_EDITOR_COMMENT => "editors_comment",
        self::TYPE_WAITING_FOR_AUTHOR_SOURCES_REQUEST => "copy_editing_waiting_for_authors_sources",
        self::TYPE_AUTHOR_SOURCES_DEPOSED_ANSWER => "copy_editing_sources_submitted",
        self::TYPE_WAITING_FOR_AUTHOR_FORMATTING_REQUEST => "copy_editing_awaiting_formatting_by_author",
        self::TYPE_AUTHOR_FORMATTING_ANSWER => 'copy_editing_final_version_submitted_awaiting_validation',
        self::TYPE_AUTHOR_FORMATTING_VALIDATED_REQUEST => 'copy_editing_formatted_version_accepted_waiting_final_version',
        self::TYPE_REVIEW_FORMATTING_DEPOSED_REQUEST => 'copy_editing_formatting_by_journal_completed_awaiting_final_version',
        self::TYPE_CE_AUTHOR_FINAL_VERSION_SUBMITTED => 'copy_editing_final_version_submitted',
        self::TYPE_ACCEPTED_ASK_AUTHOR_VALIDATION => "copy_editing_accepted_waiting_validation_by_author"
    ];

    public static array $copyEditingRequestTypes = [
        self::TYPE_WAITING_FOR_AUTHOR_SOURCES_REQUEST,
        self::TYPE_WAITING_FOR_AUTHOR_FORMATTING_REQUEST,
        self::TYPE_AUTHOR_FORMATTING_VALIDATED_REQUEST,
        self::TYPE_REVIEW_FORMATTING_DEPOSED_REQUEST
    ];

    public static array $copyEditingAnswerTypes = [
        self::TYPE_AUTHOR_SOURCES_DEPOSED_ANSWER,
        self::TYPE_AUTHOR_FORMATTING_ANSWER,
        self::TYPE_CE_AUTHOR_FINAL_VERSION_SUBMITTED
    ];




    /**
     * @var int
     */
    #[ORM\Column(name: 'PCID', type: 'integer', nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
        ]
    )]
    private int $pcid;


    #[ORM\Column(name: 'PARENTID', type: 'integer', nullable: true, options: ['unsigned' => true])]
    #[groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
        ]
    )]
    private ?int $parentid;

    /**
     * @var int
     */
    #[ORM\Column(name: 'TYPE', type: 'integer', nullable: false, options: ['unsigned' => true])]
    #[groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
        ]
    )]
    private int $type;
    #[groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
        ]
    )]
    private string $typeLabel;

    /**
     * @var int
     */
    #[ORM\Column(name: 'DOCID', type: 'integer', nullable: false, options: ['unsigned' => true])]
    private int $docid;

    /**
     * @var int
     */
    #[ORM\Column(name: 'UID', type: 'integer', nullable: false, options: ['unsigned' => true])]
    #[groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
        ]
    )]
    private int $uid;


    #[ORM\Column(name: 'MESSAGE', type: 'text', length: 16777215, nullable: true)]
    #[groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
        ]
    )]
    private ?string $message;


    #[ORM\Column(name: 'FILE', type: 'string', length: 200, nullable: true)]
    #[groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
        ]
    )]
    private ?string $file;

    #[groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
        ]
    )]
    private ?array $fileContent = null;


    #[ORM\Column(name: 'WHEN', type: 'datetime', nullable: false)]
    #[groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
        ]
    )]
    private DateTimeInterface $when;


    #[ORM\Column(name: 'DEADLINE', type: 'date', nullable: true)]
    #[groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
        ]
    )]
    private ?DateTimeInterface $deadline;


    #[ORM\Column(name: 'OPTIONS', type: 'json', nullable: true)]

    private ?array $options;

    #[ORM\ManyToOne(inversedBy: 'comments')]
    #[ORM\JoinColumn(name: 'docid', referencedColumnName: 'DOCID', nullable: false)]
    private ?Paper $paper = null;
    
    
    private ?string $path = null;
    private bool $isCopyEditingComment = false;

    public function getPcid(): int
    {
        return $this->pcid;
    }

    public function getParentid(): ?int
    {
        return $this->parentid;
    }

    public function setParentid(?int $parentId = null): self
    {
        $this->parentid = $parentId;

        return $this;
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function getTypeLabel(): string
    {
        return $this->typeLabel;
    }


    public function setType(int $type): self
    {
        $this->type = $type;
        $this->typeLabel = self::TYPE_LABEL[$this->type];
        return $this;
    }

    public function getDocid(): int
    {
        return $this->docid;
    }

    public function setDocid(int $docId): self
    {
        $this->docid = $docId;

        return $this;
    }

    public function getUid(): int
    {
        return $this->uid;
    }

    public function setUid(int $uid): self
    {
        $this->uid = $uid;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message = null): self
    {
        $this->message = $message;

        return $this;
    }

    public function getFile(): ?string
    {
        return $this->file;
    }

    public function setFile(?string $file = null): self
    {
        $this->file = $file;

        return $this;
    }

    public function getWhen(): ?DateTimeInterface
    {
        return $this->when;
    }

    public function setWhen(DateTimeInterface $when): self
    {
        $this->when = $when;

        return $this;
    }

    public function getDeadline(): ?DateTimeInterface
    {
        return $this->deadline;
    }

    public function setDeadline(DateTimeInterface $deadline = null): self
    {
        $this->deadline = $deadline;

        return $this;
    }

    public function getOptions(): ?array
    {
        return $this->options;
    }

    public function setOptions(?array $options = null): self
    {
        $this->options = $options;

        return $this;
    }

    public function getPaper(): Paper
    {
        return $this->paper;
    }

    public function setPaper(Paper $paper): static
    {
        $this->paper = $paper;
        return $this;
    }

    public function setTypeLabel(string $typeLabel): static
    {
        $this->typeLabel = $typeLabel;
        return $this;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(?string $path): self
    {
        $this->path = $path;
        return $this;
    }

    public function isCopyEditingComment(): bool
    {
        if (!$this->isCopyEditingComment) {
            return in_array($this->getType(), array_merge(static::$copyEditingRequestTypes, static::$copyEditingAnswerTypes), true);
        }

        return $this->isCopyEditingComment;
    }

    public function getFileContent(): ?array
    {
        return $this->fileContent;
    }

    public function setFileContent( ?array $fileContent = []): self
    {
        $this->fileContent = $fileContent;
        return $this;
    }

}
