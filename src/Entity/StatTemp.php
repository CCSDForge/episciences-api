<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * StatTemp
 */
#[ORM\Entity]
#[ORM\Table(name: 'STAT_TEMP')]
#[ORM\Index(name: 'DOCID', columns: ['DOCID'])]
class StatTemp
{
    /**
     * @var int
     */
    #[ORM\Column(name: 'VISITID', type: 'integer', nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private $visitid;

    #[ORM\Column(name: 'DOCID', type: 'integer', nullable: false, options: ['unsigned' => true])]
    private ?int $docid = null;

    #[ORM\Column(name: 'IP', type: 'integer', nullable: false, options: ['unsigned' => true])]
    private ?int $ip = null;

    #[ORM\Column(name: 'HTTP_USER_AGENT', type: 'string', length: 2000, nullable: false)]
    private ?string $httpUserAgent = null;

    #[ORM\Column(name: 'DHIT', type: 'datetime', nullable: false)]
    private \DateTime|\DateTimeInterface $dhit;

    #[ORM\Column(name: 'CONSULT', type: 'string', length: 0, nullable: false, options: ['default' => 'notice'])]
    private string $consult = 'notice';
    public function __construct()
    {
        $this->dhit = new \DateTime('CURRENT_TIMESTAMP');
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
