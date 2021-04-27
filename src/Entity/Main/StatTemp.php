<?php

namespace App\Entity\Main;

use Doctrine\ORM\Mapping as ORM;

/**
 * StatTemp
 *
 * @ORM\Table(name="STAT_TEMP", indexes={@ORM\Index(name="DOCID", columns={"DOCID"})})
 * @ORM\Entity
 */
class StatTemp
{
    /**
     * @var int
     *
     * @ORM\Column(name="VISITID", type="integer", nullable=false, options={"unsigned"=true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $visitid;

    /**
     * @var int
     *
     * @ORM\Column(name="DOCID", type="integer", nullable=false, options={"unsigned"=true})
     */
    private $docid;

    /**
     * @var int
     *
     * @ORM\Column(name="IP", type="integer", nullable=false, options={"unsigned"=true})
     */
    private $ip;

    /**
     * @var string
     *
     * @ORM\Column(name="HTTP_USER_AGENT", type="string", length=2000, nullable=false)
     */
    private $httpUserAgent;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="DHIT", type="datetime", nullable=false, options={"default"="CURRENT_TIMESTAMP"})
     */
    private $dhit = 'CURRENT_TIMESTAMP';

    /**
     * @var string
     *
     * @ORM\Column(name="CONSULT", type="string", length=0, nullable=false, options={"default"="notice"})
     */
    private $consult = 'notice';

    public function getVisitid(): ?int
    {
        return $this->visitid;
    }

    public function getDocid(): ?int
    {
        return $this->docid;
    }

    public function setDocid(int $docid): self
    {
        $this->docid = $docid;

        return $this;
    }

    public function getIp(): ?int
    {
        return $this->ip;
    }

    public function setIp(int $ip): self
    {
        $this->ip = $ip;

        return $this;
    }

    public function getHttpUserAgent(): ?string
    {
        return $this->httpUserAgent;
    }

    public function setHttpUserAgent(string $httpUserAgent): self
    {
        $this->httpUserAgent = $httpUserAgent;

        return $this;
    }

    public function getDhit(): ?\DateTimeInterface
    {
        return $this->dhit;
    }

    public function setDhit(\DateTimeInterface $dhit): self
    {
        $this->dhit = $dhit;

        return $this;
    }

    public function getConsult(): ?string
    {
        return $this->consult;
    }

    public function setConsult(string $consult): self
    {
        $this->consult = $consult;

        return $this;
    }


}
