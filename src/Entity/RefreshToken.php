<?php

namespace App\Entity;

use App\Repository\RefreshTokenRepository;
use Container31G4Cwd\get_Debug_ValueResolver_ArgumentResolver_DefaultService;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gesdinet\JWTRefreshTokenBundle\Model\AbstractRefreshToken as BaseRefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;


#[ORM\Entity(repositoryClass: RefreshTokenRepository::class)]
#[ORM\Table(name: "refresh_tokens")]
#[ORM\UniqueConstraint(columns: ['refreshToken'])]
class RefreshToken extends BaseRefreshToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'id', type: 'integer')]
    protected $id;


    /**
     * @var string|null
     */
    #[ORM\Column(name: 'refreshToken', type: 'string')]
    protected $refreshToken;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'username', type: 'string')]
    protected $username;

    /**
     * @var \DateTimeInterface|null
     */
    #[ORM\Column(name: 'valid', type: 'datetime')]
    protected $valid;

    #[ORM\Column(name: 'rvid', nullable: true)]
    private ?int $rvId = null;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $date = null;



    public static function createForUserWithTtl(string $refreshToken, User|UserInterface $user, int $ttl): RefreshTokenInterface
    {
        /** @var  $model static */
        $model = Parent::createForUserWithTtl($refreshToken, $user, $ttl);
        $model->setRvid($user->getCurrentJournalID());
        $model->setDate();

        return $model;
    }

    public function getRvId(): ?int
    {
        return $this->rvId;
    }

    public function setRvId(?int $rvId): static
    {
        $this->rvId = $rvId;

        return $this;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(): static
    {
        $this->date = new DateTime("now");
        return $this;
    }
}
