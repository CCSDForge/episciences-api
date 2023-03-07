<?php

namespace App\Entity;

use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\Mapping as ORM;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken as BaseRefreshToken;


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
}
