<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * StatTemp
 */
#[ORM\Entity]
#[ORM\Table(name: 'STAT_TEMP')]
#[ORM\Index(columns: ['DOCID'], name: 'DOCID')]
class StatTemp
{
    #[ORM\Column(name: 'VISITID', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private int $visitid;

    #[ORM\Column(name: 'DOCID', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    private int $docid;

    #[ORM\Column(name: 'IP', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    private int $ip;

    #[ORM\Column(name: 'HTTP_USER_AGENT', type: \Doctrine\DBAL\Types\Types::STRING, length: 2000, nullable: false)]
    private string $httpUserAgent;

    /**
     * @var \DateTime
     */
    #[ORM\Column(name: 'DHIT', type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE, nullable: false)]
    private \DateTimeInterface $dhit;

    #[ORM\Column(name: 'CONSULT', type: \Doctrine\DBAL\Types\Types::STRING, length: 0, nullable: false, options: ['default' => 'notice'])]
    private string $consult = 'notice';
    public function __construct()
    {
        $this->dhit = new \DateTime();
    }

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
