<?php

namespace App\Entity\Customer;

use Doctrine\ORM\Mapping as ORM;

/**
 * SuLog
 *
 * @ORM\Table(name="SU_LOG", indexes={@ORM\Index(name="ACTION", columns={"ACTION"}), @ORM\Index(name="FROM_UID", columns={"FROM_UID", "TO_UID", "APPLICATION"})})
 * @ORM\Entity
 */
class SuLog
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
     * @var int
     *
     * @ORM\Column(name="FROM_UID", type="integer", nullable=false, options={"unsigned"=true})
     */
    private $fromUid;

    /**
     * @var int
     *
     * @ORM\Column(name="TO_UID", type="integer", nullable=false, options={"unsigned"=true})
     */
    private $toUid;

    /**
     * @var string
     *
     * @ORM\Column(name="APPLICATION", type="string", length=50, nullable=false)
     */
    private $application;

    /**
     * @var string|null
     *
     * @ORM\Column(name="ACTION", type="string", length=0, nullable=true)
     */
    private $action;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="SU_TIME", type="datetime", nullable=false, options={"default"="CURRENT_TIMESTAMP"})
     */
    private $suTime = 'CURRENT_TIMESTAMP';


}
