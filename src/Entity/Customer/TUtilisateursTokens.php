<?php

namespace App\Entity\Customer;

use Doctrine\ORM\Mapping as ORM;

/**
 * TUtilisateursTokens
 *
 * @ORM\Table(name="T_UTILISATEURS_TOKENS", uniqueConstraints={@ORM\UniqueConstraint(name="TOKEN", columns={"TOKEN"})}, indexes={@ORM\Index(name="USAGE", columns={"USAGE"})})
 * @ORM\Entity
 */
class TUtilisateursTokens
{
    /**
     * @var int
     *
     * @ORM\Column(name="UID", type="integer", nullable=false, options={"unsigned"=true})
     */
    private $uid;

    /**
     * @var string
     *
     * @ORM\Column(name="EMAIL", type="string", length=100, nullable=false, options={"comment"="E-mail auquel le jeton est envoyé"})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $email;

    /**
     * @var string
     *
     * @ORM\Column(name="TOKEN", type="string", length=40, nullable=false, options={"comment"="Jeton à usage unique"})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $token;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="TIME_MODIFIED", type="datetime", nullable=false, options={"default"="CURRENT_TIMESTAMP"})
     */
    private $timeModified = 'CURRENT_TIMESTAMP';

    /**
     * @var array
     *
     * @ORM\Column(name="USAGE", type="simple_array", length=0, nullable=false, options={"comment"="Jeton pour mot de passe perdu ou validation de compte"})
     */
    private $usage;


}
