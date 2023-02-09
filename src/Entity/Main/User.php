<?php
declare(strict_types=1);

namespace App\Entity\Main;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Lexik\Bundle\JWTAuthenticationBundle\Security\User\JWTUserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use App\Resource\StatResource;
use App\DataProvider\UsersStatsDataProvider;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use Symfony\Component\Serializer\Annotation\Groups;
use App\Controller\MeController;

/**
 * User
 *
 * @ApiResource(
 *     attributes={
 *          "denormalization_context"={"groups"={"write:user"}},
 *          "order"={"uid":"DESC"},
 *     },
 *     paginationItemsPerPage=10,
 *     paginationMaximumItemsPerPage=10,
 *     collectionOperations={
 *          "me"={
 *                "normalization_context"={"groups"={"read:user"}},
 *                "pagination_enabled"=false,
 *                 "path"="/me",
 *                 "method"="GET",
 *                 "controller"=MeController::class,
 *                 "read"=false,
 *                 "openapi_context"={
 *                 "summary"="My Account",
 *                 "security"={
 *                      {
 *                          "bearerAuth"={"bearerAuth"= {}}
 *                      }
 *                  }
 *                 }
 *               },
 *          "get"={
 *          "security"="is_granted('ROLE_SECRETARY')",
 *          "normalization_context"={"groups"={"read:user"}},
 *          "openapi_context"={
 *                  "security"={
 *                      {
 *                          "bearerAuth"={}
 *                      }
 *                  }
 *           },
 *          },
 *          "get_stats_nb_users"={
 *              "method"="GET",
 *              "output"=StatResource::class,
 *              "path"="/users/stats/nb-users",
 *              "dataProvider"=UsersStatsDataProvider::class,
 *          },
 *     },
 *     itemOperations={
 *          "get"={
 *              "security"="is_granted('ROLE_SECRETARY') or object == user",
 *              "normalization_context"={
 *                  "groups"={
 *                      "read:user:details"
 *                  }
 *              },
 *               "openapi_context"={
 *                  "security"={
 *                      {
 *                          "bearerAuth"={}
 *                      }
 *                  }
 *              }
 *
 *          },
 *     }
 *
 *
 * )
 * @ORM\Table(name="USER")
 * @ORM\Entity(repositoryClass="App\Repository\Main\UserRepository")
 * @ApiFilter(SearchFilter::class,
 *     properties={
 *      "uid": "exact",
 *      "userRoles.rvid": "exact",
 *     })
 *
 *
 */
class User implements UserInterface, PasswordAuthenticatedUserInterface, JWTUserInterface
{
    /**
     * @var int
     *
     * @ORM\Column(name="UID", type="integer", nullable=false, options={"unsigned"=true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private int $uid;

    /**
     * @var string
     *
     * @ORM\Column(name="LANGUEID", type="string", length=2, nullable=false, options={"default"="fr"})
     * @Groups({"read:user:details"})
     *
     */
    private string $langueid = 'fr';

    /**
     * @var string
     *
     * @ORM\Column(name="SCREEN_NAME", type="string", length=250, nullable=false)
     * @Groups({"read:user", "read:user:details"})
     *
     */
    private string $screenName;

    /**
     * @ORM\Column(name="USERNAME", type="string", length=100, nullable=false)
     * @Groups({"read:user", "read:user:details"})
     * @ApiProperty(security="is_granted('ROLE_SECRETARY') or object == user")
     *
     */
    private ?string $username = '';

    /**
     * @ORM\Column(name="API_PASSWORD", type="string", length=255)
     */
    private ?string $password;

    /**
     * @var string
     *
     * @ORM\Column(name="EMAIL", type="string", length=320, nullable=false, options={})
     * @ApiProperty(security="is_granted('ROLE_SECRETARY') or object == user")
     */
    private $email;

    /**
     * @var string|null
     *
     * @ORM\Column(name="CIV", type="string", length=255, nullable=true)
     * @Groups({"read:user:details"})
     */
    private $civ;

    /**
     * @var string
     *
     * @ORM\Column(name="LASTNAME", type="string", length=100, nullable=false)
     * @Groups({"read:user:details"})
     */
    private $lastname;

    /**
     * @var string|null
     *
     * @ORM\Column(name="FIRSTNAME", type="string", length=100, nullable=true)
     * @Groups({"read:user:details"})
     */
    private $firstname;

    /**
     * @var string|null
     *
     * @ORM\Column(name="MIDDLENAME", type="string", length=100, nullable=true)
     * @Groups({"read:user:details"})
     */
    private $middlename;

    /**
     * @var DateTime|null
     *
     * @ORM\Column(name="REGISTRATION_DATE", type="datetime", nullable=true, options={"comment"="Date création du compte"})
     * @ApiProperty(security="is_granted('ROLE_SECRETARY')")
     */
    private $registrationDate;

    /**
     * @var DateTime|null
     *
     * @ORM\Column(name="MODIFICATION_DATE", type="datetime", nullable=true, options={"default"="CURRENT_TIMESTAMP","comment"="Date modification du compte"})
     */
    private $modificationDate = 'CURRENT_TIMESTAMP';

    /**
     * @var bool
     *
     * @ORM\Column(name="IS_VALID", type="boolean", nullable=false)
     * @ApiProperty(security="is_granted('ROLE_SECRETARY')")
     */
    private bool $isValid = true;

    /**
     * @ORM\OneToMany(targetEntity=UserRoles::class, mappedBy="user", orphanRemoval=true)
     */
    private Collection $userRoles;

    /**
     * @ORM\OneToMany(targetEntity=Papers::class, mappedBy="author")
     * @Groups({"read:user:details"})
     *
     */
    private Collection $papers;

    /**
     * @ApiProperty(security="is_granted('ROLE_SECRETARY') or object == user")
     * @Groups({"read:user", "read:user:details"})
     */
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
    public function setEmail(string $email): self
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
    public function setCiv(?string $civ): self
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
    public function setLastname(string $lastname): self
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
    public function setFirstname(?string $firstname): self
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
    public function setMiddlename(?string $middlename): self
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
    public function setRegistrationDate(?DateTime $registrationDate): self
    {
        $this->registrationDate = $registrationDate;
        return $this;
    }

    /**
     * @return DateTime|null
     */
    public function getModificationDate()
    {
        return $this->modificationDate;
    }

    /**
     * @param DateTime|null $modificationDate
     * @return User
     */
    public function setModificationDate(?DateTime $modificationDate): self
    {
        $this->modificationDate = $modificationDate;
        return $this;
    }

    /**
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->isValid;
    }

    /**
     * @param bool $isValid
     * @return User
     */
    public function setIsValid(bool $isValid): self
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

    /**
     * @return Collection|Papers[]
     */
    public function getPapers(): Collection
    {
        return $this->papers;
    }

    public function addPaper(Papers $paper): self
    {
        if (!$this->papers->contains($paper)) {
            $this->papers[] = $paper;
            $paper->setAuthor($this);
        }

        return $this;
    }

    public function removePaper(Papers $paper): self
    {
        // set the owning side to null (unless already changed)
        if ($this->papers->removeElement($paper) && $paper->getAuthor() === $this) {
            $paper->setAuthor(null);
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

        $elements = $this->userRoles->toArray();

        /* @var UserRoles $userRole */
        foreach ($elements as $userRole) {
            $roles[$userRole->getRvid()][] = 'ROLE_' . strtoupper($userRole->getRoleid());
        }

        return !$rvId ? ['ROLE_MEMBER'] : $roles[$rvId];

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

    public static function createFromPayload($uid, array $payload): User
    {
        $user = new User();
        $user->setUid((int)$uid);
        $user->setUsername($payload['username'] ?? '');
        $user->setRoles($payload['roles'] ?? []);
        return $user;
    }

    public function getUserIdentifier(): ?string
    {
        return $this->getUsername();
    }
}
