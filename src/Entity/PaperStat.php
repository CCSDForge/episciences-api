<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * PaperStat
 */
#[ORM\Entity]
#[ORM\Table(name: 'PAPER_STAT')]
class PaperStat
{
    #[ORM\Column(name: 'DOCID', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private int $docid;

    #[ORM\Column(name: 'CONSULT', type: \Doctrine\DBAL\Types\Types::STRING, length: 0, nullable: false, options: ['default' => 'notice'])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private string $consult = 'notice';

    #[ORM\Column(name: 'IP', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private int $ip;

    #[ORM\Column(name: 'ROBOT', type: \Doctrine\DBAL\Types\Types::BOOLEAN, nullable: false)]
    private bool $robot;

    #[ORM\Column(name: 'AGENT', type: \Doctrine\DBAL\Types\Types::STRING, length: 2000, nullable: true)]
    private ?string $agent = null;

    #[ORM\Column(name: 'DOMAIN', type: \Doctrine\DBAL\Types\Types::STRING, length: 100, nullable: true)]
    private ?string $domain = null;

    #[ORM\Column(name: 'CONTINENT', type: \Doctrine\DBAL\Types\Types::STRING, length: 100, nullable: true)]
    private ?string $continent = null;

    #[ORM\Column(name: 'COUNTRY', type: \Doctrine\DBAL\Types\Types::STRING, length: 100, nullable: true)]
    private ?string $country = null;

    #[ORM\Column(name: 'CITY', type: \Doctrine\DBAL\Types\Types::STRING, length: 100, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(name: 'LAT', type: \Doctrine\DBAL\Types\Types::FLOAT, precision: 10, scale: 0, nullable: true)]
    private ?float $lat = null;

    #[ORM\Column(name: 'LON', type: \Doctrine\DBAL\Types\Types::FLOAT, precision: 10, scale: 0, nullable: true)]
    private ?float $lon = null;

    /**
     * @var \DateTime
     */
    #[ORM\Column(name: 'HIT', type: \Doctrine\DBAL\Types\Types::DATE_MUTABLE, nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private \DateTimeInterface $hit;

    #[ORM\Column(name: 'COUNTER', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    private int $counter;

    public function getDocid(): ?int
    {
        return $this->docid;
    }

    public function getConsult(): ?string
    {
        return $this->consult;
    }

    public function getIp(): ?int
    {
        return $this->ip;
    }

    public function getRobot(): ?bool
    {
        return $this->robot;
    }

    public function setRobot(bool $robot): self
    {
        $this->robot = $robot;

        return $this;
    }

    public function getAgent(): ?string
    {
        return $this->agent;
    }

    public function setAgent(?string $agent): self
    {
        $this->agent = $agent;

        return $this;
    }

    public function getDomain(): ?string
    {
        return $this->domain;
    }

    public function setDomain(?string $domain): self
    {
        $this->domain = $domain;

        return $this;
    }

    public function getContinent(): ?string
    {
        return $this->continent;
    }

    public function setContinent(?string $continent): self
    {
        $this->continent = $continent;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): self
    {
        $this->country = $country;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): self
    {
        $this->city = $city;

        return $this;
    }

    public function getLat(): ?float
    {
        return $this->lat;
    }

    public function setLat(?float $lat): self
    {
        $this->lat = $lat;

        return $this;
    }

    public function getLon(): ?float
    {
        return $this->lon;
    }

    public function setLon(?float $lon): self
    {
        $this->lon = $lon;

        return $this;
    }

    public function getHit(): ?\DateTimeInterface
    {
        return $this->hit;
    }

    public function getCounter(): ?int
    {
        return $this->counter;
    }

    public function setCounter(int $counter): self
    {
        $this->counter = $counter;

        return $this;
    }


}
