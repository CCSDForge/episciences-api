<?php

namespace App\Entity\Main;

use Doctrine\ORM\Mapping as ORM;

/**
 * UserRoles
 *
 * @ORM\Table(name="USER_ROLES")
 * @ORM\Entity(repositoryClass="App\Repository\Main\UserRolesRepository")
 */
class UserRoles
{
    /**
     * @var int
     *
     * @ORM\Column(name="UID", type="integer", nullable=false, options={"unsigned"=true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $uid;

    /**
     * @var int
     *
     * @ORM\Column(name="RVID", type="integer", nullable=false, options={"unsigned"=true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $rvid;

    /**
     * @var string
     *
     * @ORM\Column(name="ROLEID", type="string", length=20, nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $roleid;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="roles")
     * @ORM\JoinColumn(name="UID", referencedColumnName="UID", nullable=true)
     */
    private ?User $user;

    public function __construct($uid, $rvid, $roleid){
        $this->uid = $uid;
        $this->rvid = $rvid;
        $this->roleid = $roleid;
    }

    public function getUid(): ?int
    {
        return $this->uid;
    }

    public function getRvid(): ?int
    {
        return $this->rvid;
    }

    public function getRoleid(): ?string
    {
        return $this->roleid;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * @param int $uid
     */
    public function setUid(int $uid): void
    {
        $this->uid = $uid;
    }

    /**
     * @param int $rvid
     */
    public function setRvid(int $rvid): void
    {
        $this->rvid = $rvid;
    }

    /**
     * @param string $roleid
     */
    public function setRoleid(string $roleid): void
    {
        $this->roleid = $roleid;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }


}
