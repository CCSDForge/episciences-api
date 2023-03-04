<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * PaperStat
 *
 * @ORM\Table(name="PAPER_STAT")
 * @ORM\Entity
 */
class PaperStat
{
    /**
     * @var int
     *
     * @ORM\Column(name="DOCID", type="integer", nullable=false, options={"unsigned"=true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $docid;

    /**
     * @var string
     *
     * @ORM\Column(name="CONSULT", type="string", length=0, nullable=false, options={"default"="notice"})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $consult = 'notice';

    /**
     * @var int
     *
     * @ORM\Column(name="IP", type="integer", nullable=false, options={"unsigned"=true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $ip;

    /**
     * @var bool
     *
     * @ORM\Column(name="ROBOT", type="boolean", nullable=false)
     */
    private $robot;

    /**
     * @var string|null
     *
     * @ORM\Column(name="AGENT", type="string", length=2000, nullable=true)
     */
    private $agent;

    /**
     * @var string|null
     *
     * @ORM\Column(name="DOMAIN", type="string", length=100, nullable=true)
     */
    private $domain;

    /**
     * @var string|null
     *
     * @ORM\Column(name="CONTINENT", type="string", length=100, nullable=true)
     */
    private $continent;

    /**
     * @var string|null
     *
     * @ORM\Column(name="COUNTRY", type="string", length=100, nullable=true)
     */
    private $country;

    /**
     * @var string|null
     *
     * @ORM\Column(name="CITY", type="string", length=100, nullable=true)
     */
    private $city;

    /**
     * @var float|null
     *
     * @ORM\Column(name="LAT", type="float", precision=10, scale=0, nullable=true)
     */
    private $lat;

    /**
     * @var float|null
     *
     * @ORM\Column(name="LON", type="float", precision=10, scale=0, nullable=true)
     */
    private $lon;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="HIT", type="date", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $hit;

    /**
     * @var int
     *
     * @ORM\Column(name="COUNTER", type="integer", nullable=false, options={"unsigned"=true})
     */
    private $counter;

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
