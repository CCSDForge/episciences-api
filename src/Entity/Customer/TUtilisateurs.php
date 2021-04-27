<?php

namespace App\Entity\Customer;

use Doctrine\ORM\Mapping as ORM;

/**
 * TUtilisateurs
 *
 * @ORM\Table(name="T_UTILISATEURS", uniqueConstraints={@ORM\UniqueConstraint(name="U_USERNAME", columns={"USERNAME"})}, indexes={@ORM\Index(name="EMAIL", columns={"EMAIL"}), @ORM\Index(name="FIRSTNAME", columns={"FIRSTNAME"}), @ORM\Index(name="LASTNAME", columns={"LASTNAME"}), @ORM\Index(name="PASSWORD", columns={"PASSWORD"}), @ORM\Index(name="VALID", columns={"VALID"})})
 * @ORM\Entity
 */
class TUtilisateurs
{
    /**
     * @var int
     *
     * @ORM\Column(name="UID", type="integer", nullable=false, options={"unsigned"=true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $uid;

    /**
     * @var string
     *
     * @ORM\Column(name="USERNAME", type="string", length=100, nullable=false)
     */
    private $username;

    /**
     * @var string
     *
     * @ORM\Column(name="PASSWORD", type="string", length=128, nullable=false)
     */
    private $password;

    /**
     * @var string
     *
     * @ORM\Column(name="EMAIL", type="string", length=320, nullable=false, options={"comment"="http://tools.ietf.org/html/rfc3696#section-3"})
     */
    private $email;

    /**
     * @var string|null
     *
     * @ORM\Column(name="CIV", type="string", length=255, nullable=true)
     */
    private $civ;

    /**
     * @var string
     *
     * @ORM\Column(name="LASTNAME", type="string", length=100, nullable=false)
     */
    private $lastname;

    /**
     * @var string|null
     *
     * @ORM\Column(name="FIRSTNAME", type="string", length=100, nullable=true)
     */
    private $firstname;

    /**
     * @var string|null
     *
     * @ORM\Column(name="MIDDLENAME", type="string", length=100, nullable=true)
     */
    private $middlename;

    /**
     * @var string|null
     *
     * @ORM\Column(name="URL", type="string", length=500, nullable=true)
     */
    private $url;

    /**
     * @var string|null
     *
     * @ORM\Column(name="PHONE", type="string", length=50, nullable=true)
     */
    private $phone;

    /**
     * @var string|null
     *
     * @ORM\Column(name="FAX", type="string", length=50, nullable=true)
     */
    private $fax;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="TIME_REGISTERED", type="datetime", nullable=true, options={"comment"="Date création du compte"})
     */
    private $timeRegistered;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="TIME_MODIFIED", type="datetime", nullable=true, options={"default"="CURRENT_TIMESTAMP","comment"="Date modification du compte"})
     */
    private $timeModified = 'CURRENT_TIMESTAMP';

    /**
     * @var string|null
     *
     * @ORM\Column(name="PHOTO", type="blob", length=16777215, nullable=true)
     */
    private $photo;

    /**
     * @var string|null
     *
     * @ORM\Column(name="FTP_HOME", type="string", length=255, nullable=true, options={"comment"="Chemin du home FTP"})
     */
    private $ftpHome;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="FTP_LAST_AUTH", type="datetime", nullable=true, options={"comment"="Dernière authentification par FTP"})
     */
    private $ftpLastAuth;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="FTP_LAST_USE", type="datetime", nullable=true, options={"comment"="Dernière utilisation du FTP"})
     */
    private $ftpLastUse;

    /**
     * @var bool
     *
     * @ORM\Column(name="VALID", type="boolean", nullable=false)
     */
    private $valid = '0';


}
