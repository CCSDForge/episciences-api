<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * UserAssignment
 *
 * @ORM\Table(name="USER_ASSIGNMENT", indexes={@ORM\Index(name="FK_ITEMID_idx", columns={"ITEMID"}), @ORM\Index(name="FK_UID_idx", columns={"UID"})})
 * @ORM\Entity
 */
class UserAssignment
{
    /**
     * @var int
     *
     * @ORM\Column(name="ID", type="integer", nullable=false, options={"unsigned"=true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var int|null
     *
     * @ORM\Column(name="INVITATION_ID", type="integer", nullable=true, options={"unsigned"=true})
     */
    private $invitationId;

    /**
     * @var int
     *
     * @ORM\Column(name="RVID", type="integer", nullable=false, options={"unsigned"=true})
     */
    private $rvid;

    /**
     * @var int
     *
     * @ORM\Column(name="ITEMID", type="integer", nullable=false, options={"unsigned"=true})
     */
    private $itemid;

    /**
     * @var string
     *
     * @ORM\Column(name="ITEM", type="string", length=50, nullable=false)
     */
    private $item;

    /**
     * @var int
     *
     * @ORM\Column(name="UID", type="integer", nullable=false, options={"unsigned"=true})
     */
    private $uid;

    /**
     * @var bool
     *
     * @ORM\Column(name="TMP_USER", type="boolean", nullable=false)
     */
    private $tmpUser;

    /**
     * @var string
     *
     * @ORM\Column(name="ROLEID", type="string", length=50, nullable=false)
     */
    private $roleid;

    /**
     * @var string
     *
     * @ORM\Column(name="STATUS", type="string", length=20, nullable=false)
     */
    private $status;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="WHEN", type="datetime", nullable=false)
     */
    private $when;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="DEADLINE", type="datetime", nullable=true)
     */
    private $deadline;

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


}
