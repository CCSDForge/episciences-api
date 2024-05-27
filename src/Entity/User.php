<?php
declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Parameter;
use App\AppConstants;
use App\Controller\PapersController;
use App\Repository\UserRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Lexik\Bundle\JWTAuthenticationBundle\Security\User\JWTUserInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\Groups;
use App\Controller\MeController;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\OpenApi\OpenApiFactory;

#[ORM\Table(name: self::TABLE)]
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ApiResource(
    operations: [
        new Get(
            openapi: new OpenApiOperation(
                security: [
                    ['bearerAuth' => []],
                ]
            ),
            normalizationContext: [
                'groups' => [AppConstants::APP_CONST['normalizationContext']['groups']['user']['item']['read'][0]]
            ],
            security: "is_granted('ROLE_SECRETARY')"
        ),
        new GetCollection(
            openapi: new OpenApiOperation(
                security: [
                    ['bearerAuth' => []],
                ]
            ),
            normalizationContext: [
                'groups' => [AppConstants::APP_CONST['normalizationContext']['groups']['user']['collection']['read'][0]]
            ],
            security: "is_granted('ROLE_SECRETARY')",
        ),
        new Get(
            uriTemplate: '/me',
            controller: MeController::class,
            openapi: new OpenApiOperation(
                tags: [OpenApiFactory::OAF_TAGS['auth']],
                summary: 'My Account',
                security: [
                    ['bearerAuth' => []],
                ]

            ),
            normalizationContext: [
                'groups' => ['read:Me']
            ],
            security: "is_granted('ROLE_USER')",
            output: [true, false],
            read: false

        ),

        new Get(
            uriTemplate: '/users/{uid}/is-allowed-to-edit-citations',
            controller: PapersController::class,
            openapi: new OpenApiOperation(
                tags: [OpenApiFactory::OAF_TAGS['user']],
                responses: [
                    Response::HTTP_OK => [
                        'description' => "is allowed to edit document's citations?",
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'isAllowed' => [
                                            'type' => 'boolean',
                                            'readOnly' => true,
                                            'default' => false
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ]

                ],
                summary: "is allowed to edit document's citations",
                description: "Check if current user is allowed to edit paper's citations",
                parameters: [
                    new Parameter(
                        name: 'documentId',
                        in: 'query',
                        description: 'Document identifier',
                        required: true,
                        allowEmptyValue: true,
                    )
                ]
            ),

            read: false, //  to bypass the automatic retrieval of the entity in your custom operation
        ),
    ],


    order: ['uid' => 'DESC'],


)]

#[ApiFilter(SearchFilter::class, properties: self::FILTERS)]
class User implements UserInterface, PasswordAuthenticatedUserInterface, JWTUserInterface
{
    public const TABLE = 'USER';
    public const ROLE_ROOT = 'epiadmin';
    public const ROLE_SECRETARY = 'secretary';
    public const ROLE_ADMINISTRATOR = 'administrator';
    public const ROLE_EDITOR_IN_CHIEF = 'chief_editor';
    public const ROLE_EDITOR = 'editor';
    public const EPISCIENCES_UID = 666;
    public const USERS_REVIEW_ID_FILTER = 'userRoles.rvid';
    public const FILTERS = [self::USERS_REVIEW_ID_FILTER => 'exact'];

    #[Groups(['read:Me',])]
    private ?int $currentJournalID = null;

    /**
     * @var array
     */
    #[Groups(['read:User', 'read:Me', 'read:Boards'])]
    private array $roles;

    #[ORM\Id]
    #[ORM\Column(name: "UID", type: "integer", nullable: false, options: ['unsigned'=> true])]
    #[ORM\GeneratedValue]
    #[Groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['user']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['user']['collection']['read'][0],
            'read:Boards'
        ])]
    //#[ApiProperty(security: "is_granted('papers_manage', object)")] //
    private int $uid;


    #[ORM\Column(name:"LANGUEID",type: "string", length: 2, nullable: false, options: ['default' => 'fr'])]
    #[Groups([
        AppConstants::APP_CONST['normalizationContext']['groups']['user']['item']['read'][0],
        AppConstants::APP_CONST['normalizationContext']['groups']['user']['collection']['read'][0],
        'read:Me','read:Boards'
    ])]
    private string $langueid = 'fr';
    #[ORM\Column(name:"SCREEN_NAME", type: 'string', length: 250, nullable: false) ]
    #[Groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['user']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['user']['collection']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['papers']['collection']['read'][0],
            'read:Me','read:Boards','read:News', 'read:News:Collection'
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
        'read:Me','read:Boards'
    ])]
    private $email;


    #[ORM\Column(name: "CIV", type: 'string', length: 255, nullable: true)]
    #[Groups([
        AppConstants::APP_CONST['normalizationContext']['groups']['user']['item']['read'][0],
        AppConstants::APP_CONST['normalizationContext']['groups']['user']['collection']['read'][0],
        'read:Me','read:Boards'
    ])]
    private $civ;

    #[ORM\Column(name: "LASTNAME", type: 'string', length: 100, nullable: false)]
    #[Groups(['read:User', 'read:Me','read:Boards'])]
    private $lastname;


    #[ORM\Column(name: "FIRSTNAME", type: 'string', length: 100, nullable: true)]
    #[Groups(['read:User', 'read:Me','read:Boards'])]
    private $firstname;

       #[ORM\Column(name: "MIDDLENAME", type: 'string', length: 100, nullable: true)]
    #[Groups(['read:User', 'read:Me','read:Boards'])]
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


    #[ORM\Column(name: "ORCID", type: 'string', length: 19, nullable: true)]
    #[Groups(['read:User', 'read:Me','read:Boards'])]
    private $orcid = null;

    #[ORM\Column(name: 'ADDITIONAL_PROFILE_INFORMATION', type: 'json', nullable: true)]
    #[Groups(['read:User', 'read:Me','read:Boards'])]
    private ?array $additionalProfileInformation;

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


    #[ORM\OneToMany(mappedBy: "user", targetEntity: Paper::class)]
    #[Groups(['read:User', 'read:Me'])]
    private Collection $papers;

    private ?string $photoPath;

    /**
     * @var Collection<int, News>
     */
    #[ORM\OneToMany(mappedBy: 'creator', targetEntity: News::class)]
    #[Groups(['read:User', 'read:Me'])]
    private Collection $news;
    #[Groups(['read:Boards'])]
    private ?array $assignedSections;

    public function __construct()
    {
        $this->roles = [];
        $this->userRoles = new ArrayCollection();
        $this->papers = new ArrayCollection();
        $this->news = new ArrayCollection();
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

    public function addPaper(Paper $paper): self
    {
        if (!$this->papers->contains($paper)) {
            $this->papers[] = $paper;
            $paper->setUser($this);
        }

        return $this;
    }

    public function removePaper(Paper $paper): self
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
        $user->setCurrentJournalID($payload['rvId']);
        return $user;
    }

    public function getUserIdentifier(): string
    {
        return $this->getUsername();
    }

    public function setCurrentJournalID(?int $currentJournalID): self
    {
        $this->currentJournalID = $currentJournalID;
        return $this;
    }

    public function getCurrentJournalID(): ?int
    {
        return $this->currentJournalID;
    }

    public function getAdditionalProfileInformation(): ?array
    {
        return $this->additionalProfileInformation;
    }

    public function setAdditionalProfileInformation(array $additionalProfileInformation = null): self
    {
        $this->additionalProfileInformation = $additionalProfileInformation;
        return $this;
    }

    public function getOrcid(): ?string
    {
        return $this->orcid;
    }

    public function setOrcid(string $orcid = null): self
    {
        $this->orcid = $orcid;
        return $this;
    }

    public function getPhotoPath():string {
        return $this->photoPath;
    }

    public function setPhotoPath(?string $photoPath = null): self
    {
        if (null === $photoPath) {
            $this->photoPath = sprintf('/user/photo/uid/%s', $this->getUid());
        } else {
            $this->photoPath = $photoPath;
        }
        return $this;
    }

    /**
     * @return Collection<int, News>
     */
    public function getNews(): Collection
    {
        return $this->news;
    }

    public function addNews(News $news): static
    {
        if (!$this->news->contains($news)) {
            $this->news->add($news);
            $news->setCreator($this);
        }

        return $this;
    }

    public function removeNews(News $news): static
    {
        if ($this->news->removeElement($news)) {
            // set the owning side to null (unless already changed)
            if ($news->getCreator() === $this) {
                $news->setCreator(null);
            }
        }

        return $this;
    }

    public function getAssignedSections(): ?array
    {
        return $this->assignedSections;
    }

    public function setAssignedSections(?array $assignedSections = null): void
    {
        $this->assignedSections = $assignedSections;
    }

}
