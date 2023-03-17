<?php
declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\AppConstants;
use App\Repository\UserRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Lexik\Bundle\JWTAuthenticationBundle\Security\User\JWTUserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Context;
use Symfony\Component\Serializer\Annotation\Groups;
use App\Controller\MeController;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\OpenApi\OpenApiFactory;


/**
 * @property int $rvId
 */
#[ORM\Table(name: self::TABLE)]
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ApiResource(
    operations: [
        new Get(
            normalizationContext: [
                'groups' => [AppConstants::APP_CONST['normalizationContext']['groups']['user']['item']['read'][0]]
            ],
            security: "is_granted('ROLE_SECRETARY') or (is_granted('ROLE_USER') and object.getUid() == user.getUid())"
        ),
        new GetCollection(
            normalizationContext: [
                'groups' => [AppConstants::APP_CONST['normalizationContext']['groups']['user']['collection']['read'][0]]
                ]
        ),
        new Get(
            uriTemplate: '/me',
            controller: MeController::class,
            openapi: new OpenApiOperation(
                tags:[OpenApiFactory::OAF_TAGS['auth']],
                summary: 'My Account',
                security: [
                    ['bearerAuth' =>  []],
                ]

            ),
            normalizationContext: [
                'groups' => ['read:Me']
            ],
            security: "is_granted('ROLE_USER')",
            read: false

        ),
    ],

    openapi: new OpenApiOperation(
        security: [
            ['bearerAuth' =>  []],
        ]
    ),
    order: ['uid' => 'DESC'],
    security: "is_granted('ROLE_SECRETARY')",

)]

class User implements UserInterface, PasswordAuthenticatedUserInterface, JWTUserInterface
{
    public const TABLE = 'USER';
    public const ROLE_ROOT = 'epiadmin';
    public const EPISCIENCES_UID = 666;

    #[ORM\Id]
    #[ORM\Column(name: "UID", type: "integer", nullable: false, options: ['unsigned'=> true])]
    #[ORM\GeneratedValue]
    #[Groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['user']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['user']['collection']['read'][0]
        ])]
    private int $uid;


    #[ORM\Column(name:"LANGUEID",type: "string", length: 2, nullable: false, options: ['default' => 'fr'])]
    #[Groups([
        AppConstants::APP_CONST['normalizationContext']['groups']['user']['item']['read'][0],
        AppConstants::APP_CONST['normalizationContext']['groups']['user']['collection']['read'][0],
        'read:Me',
    ])]
    private string $langueid = 'fr';
    #[ORM\Column(name:"SCREEN_NAME", type: 'string', length: 250, nullable: false) ]
    #[Groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['user']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['user']['collection']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
            'read:Me'
        ])]
    private string $screenName;


    #[ORM\Column(name:"USERNAME", type: 'string', length: 100, nullable: false)]
    #[ApiProperty(security: "is_granted('ROLE_MEMBER')")]
    #[Groups(['read:Me'])]
    private ?string $username = '';


    #[ORM\Column(name: "API_PASSWORD", type: 'string', length: 255)]
    private ?string $password;


    #[ORM\Column(name: "EMAIL", type: 'string', length: 320,  nullable: false, options: [])]
    #[Groups([
        AppConstants::APP_CONST['normalizationContext']['groups']['user']['item']['read'][0],
        AppConstants::APP_CONST['normalizationContext']['groups']['user']['collection']['read'][0],
        'read:Me',
    ])]
    private $email;


    #[ORM\Column(name: "CIV", type: 'string', length: 255, nullable: true)]
    #[Groups([
        AppConstants::APP_CONST['normalizationContext']['groups']['user']['item']['read'][0],
        AppConstants::APP_CONST['normalizationContext']['groups']['user']['collection']['read'][0],
        'read:Me',
    ])]
    private $civ;

    #[ORM\Column(name: "LASTNAME", type: 'string', length: 100, nullable: false)]
    #[Groups(['read:User', 'read:Me'])]
    private $lastname;


    #[ORM\Column(name: "FIRSTNAME", type: 'string', length: 100, nullable: true)]
    #[Groups(['read:User', 'read:Me'])]
    private $firstname;

       #[ORM\Column(name: "MIDDLENAME", type: 'string', length: 100, nullable: true)]
    #[Groups(['read:User', 'read:Me'])]
    private $middlename;


    #[ORM\Column(
        name:"REGISTRATION_DATE", type: "datetime", nullable: true, options: ['comment' => 'Date de création du compte']
    )]
    #[ApiProperty(security: "is_granted('ROLE_EPIADMIN')")]
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    private $registrationDate;


    #[ORM\Column(name: "MODIFICATION_DATE", type: 'datetime', nullable: true, options: [
        'default' => 'CURRENT_TIMESTAMP', 'comment' => 'Date de modification du compte'])
    ]
    #[ApiProperty(security: "is_granted('ROLE_EPIADMIN')")]
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    private $modificationDate;


    #[ORM\Column(name: "IS_VALID", type: "boolean", nullable: false)]
    #[ApiProperty(security: "is_granted('ROLE_EPIADMIN')")]
    #[Groups(['read:User', 'read:Me'])]
    private bool $isValid = true;


    #[ORM\OneToMany(mappedBy: "user", targetEntity: UserRoles::class, orphanRemoval: true)]
    #[Groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['user']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['user']['collection']['read'][0],
            'read:Me',
        ])]
    private Collection $userRoles;


    #[ORM\OneToMany(mappedBy: "user", targetEntity: Papers::class)]
    #[Groups(['read:User', 'read:Me'])]
    private Collection $papers;

    /**
     * @var array
     */
    #[Groups(['read:User', 'read:Me'])]
    private array $roles;

    public function __construct()
    {
        $this->roles = [];
        $this->userRoles = new ArrayCollection();
        $this->papers = new ArrayCollection();
    }

    public function getUid(): ?int
    {
        return $this->uid;
    }

    public function getLangueid(): ?string
    {
        return $this->langueid;
    }

    public function setLangueid(string $langueid): self
    {
        $this->langueid = $langueid;

        return $this;
    }

    public function getScreenName(): ?string
    {
        return $this->screenName;
    }

    public function setScreenName(string $screenName): self
    {
        $this->screenName = $screenName;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username = null): self
    {
        $this->username = $username;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @param string $email
     * @return User
     */
    public function setEmail(string $email): User
    {
        $this->email = $email;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getCiv(): ?string
    {
        return $this->civ;
    }

    /**
     * @param string|null $civ
     * @return User
     */
    public function setCiv(?string $civ): User
    {
        $this->civ = $civ;
        return $this;
    }

    /**
     * @return string
     */
    public function getLastname(): string
    {
        return $this->lastname;
    }

    /**
     * @param string $lastname
     * @return User
     */
    public function setLastname(string $lastname): User
    {
        $this->lastname = $lastname;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    /**
     * @param string|null $firstname
     * @return User
     */
    public function setFirstname(?string $firstname): User
    {
        $this->firstname = $firstname;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getMiddlename(): ?string
    {
        return $this->middlename;
    }

    /**
     * @param string|null $middlename
     * @return User
     */
    public function setMiddlename(?string $middlename): User
    {
        $this->middlename = $middlename;
        return $this;
    }

    /**
     * @return DateTime|null
     */
    public function getRegistrationDate(): ?DateTime
    {
        return $this->registrationDate;
    }

    /**
     * @param DateTime|null $registrationDate
     * @return User
     */
    public function setRegistrationDate(?DateTime $registrationDate): User
    {
        $this->registrationDate = $registrationDate;
        return $this;
    }

    /**
     * @return DateTime|null
     */
    public function getModificationDate(): ?DateTime
    {
        return $this->modificationDate;
    }

    /**
     * @param DateTime|null $modificationDate
     * @return User
     */
    public function setModificationDate(?DateTime $modificationDate): User
    {
        $this->modificationDate = $modificationDate;
        return $this;
    }

    /**
     * @return bool
     */
    #[ApiProperty(security: "is_granted('ROLE_EPIADMIN')")]
    #[Groups(['read:User', 'read:Me'])]
    public function getIsValid(): bool
    {
        return $this->isValid;
    }

    /**
     * @param bool $isValid
     * @return User
     */
    public function setIsValid(bool $isValid): User
    {
        $this->isValid = $isValid;
        return $this;
    }


    /**
     * @return Collection
     */
    public function getUserRoles(): Collection
    {
        return $this->userRoles;
    }

    public function getSalt(): ?string
    {
        return null;
    }

    public function eraseCredentials(): void
    {
        // TODO: Implement eraseCredentials() method.
    }

    public function addUserRoles(UserRoles $userRole): self
    {
        if (!$this->userRoles->contains($userRole)) {
            $this->userRoles[] = $userRole;
            $userRole->setUser($this);
        }

        return $this;
    }

    public function removeRole(UserRoles $userRole): self
    {
        // set the owning side to null (unless already changed)
        if ($this->userRoles->removeElement($userRole) && $userRole->getUser() === $this) {
            $userRole->setUser(null);
        }

        return $this;
    }


    public function getPapers(): Collection
    {
        return $this->papers;
    }

    public function addPaper(Papers $paper): self
    {
        if (!$this->papers->contains($paper)) {
            $this->papers[] = $paper;
            $paper->setUser($this);
        }

        return $this;
    }

    public function removePaper(Papers $paper): self
    {
        // set the owning side to null (unless already changed)
        if ($this->papers->removeElement($paper) && $paper->getUser() === $this) {
            $paper->setUser(null);
        }

        return $this;
    }


    /**
     * @param $roleId
     * @param int $rvId
     * @return bool
     */
    public function hasRole($roleId, int $rvId): bool
    {
        return in_array('ROLE_' . strtoupper($roleId), $this->getRoles($rvId), true);
    }

    /**
     * @param int|null $rvId
     * @return array
     */
    public function getRoles(int $rvId = null): array
    {
        if (empty($this->roles)) {
            return $this->rolesProcessing($rvId);
        }

        return $this->roles;
    }

    private function rolesProcessing(int $rvId = null): array
    {
        $roles = [];
        $prefix = 'ROLE_';

        $elements = $this->userRoles->toArray();

        /* @var UserRoles $userRole */
        foreach ($elements as $userRole) {

            $currentRole = $prefix . strtoupper($userRole->getRoleid());

            if ($currentRole === $prefix . strtoupper(self::ROLE_ROOT)){
                $roles[] = $currentRole;
                return $roles;
            }

            $roles[$userRole->getRvid()][] = $currentRole;
        }
        return ($rvId === null || !array_key_exists($rvId, $roles)) ? ['ROLE_USER'] : $roles[$rvId];
    }

    public function setRoles(array $roles = []): self
    {
        $this->roles = $roles;
        return $this;

    }

    /**
     * @param int $uid
     * @return User
     */
    public function setUid(int $uid): User
    {
        $this->uid = $uid;
        return $this;
    }

    public static function createFromPayload($username, array $payload): User
    {
        $user = new User();
        $user->setUid($payload['uid'] ?? null);
        $user->setUsername($payload['username'] ??  null);
        $user->setRoles($payload['roles'] ?? []);
        $user->rvId = $payload['rvId'];
        return $user;
    }

    public function getUserIdentifier(): string
    {
        return $this->getUsername();
    }
}
