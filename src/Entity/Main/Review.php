<?php

namespace App\Entity\Main;

use ApiPlatform\Metadata\ApiResource;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Resource\StatResource;
use App\DataProvider\ReviewStatsDataProvider;


/**
 * Review
 *
 * @ORM\Table(name="REVIEW", uniqueConstraints={@ORM\UniqueConstraint(name="U_CODE", columns={"CODE"})})
 * @ORM\Entity
 */
class Review
{
    /**
     * @var int
     *
     * @ORM\Column(name="RVID", type="integer", nullable=false, options={"unsigned"=true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private int $rvid;

    /**
     * @var string
     *
     * @ORM\Column(name="CODE", type="string", length=50, nullable=false)
     */
    private string $code;

    /**
     * @var string
     *
     * @ORM\Column(name="NAME", type="string", length=2000, nullable=false)
     */
    private string $name;

    /**
     * @var int
     *
     * @ORM\Column(name="STATUS", type="smallint", nullable=false, options={"unsigned"=true})
     */
    private int $status;

    /**
     * @var DateTimeInterface
     *
     * @ORM\Column(name="CREATION", type="datetime", nullable=false)
     */
    private DateTimeInterface $creation;

    /**
     * @var int
     *
     * @ORM\Column(name="PIWIKID", type="integer", nullable=false, options={"unsigned"=true})
     */
    private int $piwikid;

    /**
     * @ORM\OneToMany(targetEntity=Papers::class, mappedBy="review")
     */
    private Collection $papers;

    public function __construct()
    {
        $this->papers = new ArrayCollection();
    }

    public function getRvid(): ?int
    {
        return $this->rvid;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function setStatus(int $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getCreation(): ?DateTimeInterface
    {
        return $this->creation;
    }

    public function setCreation(DateTimeInterface $creation): self
    {
        $this->creation = $creation;

        return $this;
    }

    public function getPiwikid(): ?int
    {
        return $this->piwikid;
    }

    public function setPiwikid(int $piwikid): self
    {
        $this->piwikid = $piwikid;

        return $this;
    }

    /**
     * @return Collection
     */
    public function getPapers(): Collection
    {
        return $this->papers;
    }

    public function addPaper(Papers $paper): self
    {
        if (!$this->papers->contains($paper)) {
            $this->papers[] = $paper;
            $paper->setReview($this);
        }

        return $this;
    }

    public function removePaper(Papers $paper): self
    {
        // set the owning side to null (unless already changed)
        if ($this->papers->removeElement($paper) && $paper->getReview() === $this) {
            $paper->setReview(null);
        }

        return $this;
    }
}
