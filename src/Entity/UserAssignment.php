<?php

namespace App\Entity;

use App\AppConstants;
use App\Repository\UserAssignmentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;


#[ORM\Table(name: 'USER_ASSIGNMENT')]
#[ORM\Index(columns: ['ITEMID"'], name: 'FK_ITEMID_idx')]
#[ORM\Index(columns: ['UID"'], name: 'FK_UID_idx')]
#[ORM\Entity(repositoryClass: UserAssignmentRepository::class)]

class UserAssignment
{

    public const ITEM_PAPER = 'paper';
    public const ITEM_SECTION = 'section';
    public const ITEM_VOLUME = 'volume';


    public const ROLE_REVIEWER = 'reviewer';
    public const ROLE_EDITOR = 'editor';
    public const ROLE_COPY_EDITOR = 'copyeditor';
    public const ROLE_CO_AUTHOR = 'coauthor';


    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_DECLINED = 'declined';

    #[ORM\Column(name: 'ID', type: 'integer', nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private $id;
    #[ORM\Column(name: 'INVITATION_ID', type: 'integer', nullable:true, options: ['unsigned' => true])]
    #[groups([
        AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
        AppConstants::APP_CONST['normalizationContext']['groups']['papers']['collection']['read'][0]
    ])]

    private $invitationId;

    #[ORM\Column(name: 'RVID', type: 'integer', nullable: false, options: ['unsigned' => true])]
    #[groups([
        AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
        AppConstants::APP_CONST['normalizationContext']['groups']['papers']['collection']['read'][0]
    ])]

    private $rvid;

    #[ORM\Column(name: 'ITEMID', type: 'integer', nullable: false, options: ['unsigned' => true])]
    #[groups([
        AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
        AppConstants::APP_CONST['normalizationContext']['groups']['papers']['collection']['read'][0]
    ])]

    private $itemid;

    #[ORM\Column(name: 'ITEM', type: 'string', length: 50, nullable: false)]
    #[groups([
        AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
        AppConstants::APP_CONST['normalizationContext']['groups']['papers']['collection']['read'][0]
    ])]

    private $item;

    #[ORM\Column(name: 'UID', type: 'integer', nullable: false, options: ['unsigned' => true])]
    private $uid;
    #[ORM\Column(name: 'TMP_USER', type: 'boolean', nullable: false)]
    #[groups([
        AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
        AppConstants::APP_CONST['normalizationContext']['groups']['papers']['collection']['read'][0]
    ])]

    private $tmpUser;

    #[ORM\Column(name: 'ROLEID', type: 'string', length: 50, nullable: false)]
    private $roleid;

    #[ORM\Column(name: 'STATUS', type: 'string', length: 20, nullable: false)]
    #[groups([
        AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
        AppConstants::APP_CONST['normalizationContext']['groups']['papers']['collection']['read'][0]
    ])]
    private $status;

    #[ORM\Column(name: 'WHEN', type: 'datetime', nullable: false)]
    #[groups([
        AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
        AppConstants::APP_CONST['normalizationContext']['groups']['papers']['collection']['read'][0]
    ])]
    private $when;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="DEADLINE", type="datetime", nullable=true)
     */
    #[ORM\Column(name: 'DEADLINE', type: 'datetime', nullable: true)]
    #[groups([
        AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
        AppConstants::APP_CONST['normalizationContext']['groups']['papers']['collection']['read'][0]
    ])]

    private $deadline;

    #[ORM\ManyToOne(inversedBy: 'assignments')]
    #[ORM\JoinColumn(name: 'ITEMID', referencedColumnName: 'DOCID', nullable: false)]
    private ?Paper $papers = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInvitationId(): ?int
    {
        return $this->invitationId;
    }

    public function setInvitationId(?int $invitationId): self
    {
        $this->invitationId = $invitationId;

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

    public function getItemid(): ?int
    {
        return $this->itemid;
    }

    public function setItemid(int $itemid): self
    {
        $this->itemid = $itemid;

        return $this;
    }

    public function getItem(): ?string
    {
        return $this->item;
    }

    public function setItem(string $item): self
    {
        $this->item = $item;

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

    public function getTmpUser(): ?bool
    {
        return $this->tmpUser;
    }

    public function setTmpUser(bool $tmpUser): self
    {
        $this->tmpUser = $tmpUser;

        return $this;
    }

    public function getRoleid(): ?string
    {
        return $this->roleid;
    }

    public function setRoleid(string $roleid): self
    {
        $this->roleid = $roleid;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getWhen(): ?\DateTimeInterface
    {
        return $this->when;
    }

    public function setWhen(\DateTimeInterface $when): self
    {
        $this->when = $when;

        return $this;
    }

    public function getDeadline(): ?\DateTimeInterface
    {
        return $this->deadline;
    }

    public function setDeadline(?\DateTimeInterface $deadline): self
    {
        $this->deadline = $deadline;

        return $this;
    }

    public function getPapers(): ?Paper
    {
        return $this->papers;
    }

    public function setPapers(?Paper $papers): self
    {
        $this->papers = $papers;

        return $this;
    }


}
