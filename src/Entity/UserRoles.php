<?php

namespace App\Entity;

use App\AppConstants;
use App\Repository\UserRolesRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;


#[ORM\Table(name: self::TABLE)]
#[ORM\Entity(repositoryClass: UserRolesRepository::class)]

class UserRoles
{
    public const TABLE = 'USER_ROLES';

    public const TECHNICAL_BOARD = 'technical_board';
    public const EDITORIAL_BOARD = 'editorial_board';
    public const SCIENTIFIC_BOARD = 'scientific_advisory_board';
    public const FORMER_MEMBER = 'former_member';

    public const ROLE_GUEST_EDITOR = 'guest_editor';

    #[ORM\Column(name: 'UID', type: 'integer', nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private $uid;



    #[ORM\Column(name: 'RVID', type: 'integer', nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    #[Groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['user']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['user']['collection']['read'][0],
            'read:Me',
        ])]
    private $rvid;


    #[ORM\Column(name: 'ROLEID', type: 'string', length: 20, nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    #[Groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['user']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['user']['collection']['read'][0],
            'read:Me',
        ])]
    private $roleid;


   #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'userRoles')]
   #[ORM\JoinColumn(name: 'UID', referencedColumnName: 'UID', nullable: true)]
    #[Groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['user']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['user']['collection']['read'][0],
            'read:Me',
        ])]
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
