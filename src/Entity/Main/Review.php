<?php

namespace App\Entity\Main;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\AppConstants;
use App\Resource\DashboardOutput;
use App\Resource\ToBeDeletedStatResource;
use App\Resource\SubmissionAcceptanceDelayOutput;
use App\Resource\SubmissionOutput;
use App\Resource\SubmissionPublicationDelayOutput;
use App\Resource\UsersStatsOutput;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\DataProvider\ReviewStatsDataProvider;
use Symfony\Component\Serializer\Annotation\Context;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use App\Repository\Main\ReviewRepository;
use App\OpenApi\OpenApiFactory;


/**
 * Review
 *
 * @ORM\Table(name="REVIEW", uniqueConstraints={@ORM\UniqueConstraint(name="U_CODE", columns={"CODE"})})
 * @ORM\Entity(repositoryClass=ReviewRepository::class)
 */
#[ApiResource(
    operations: [
        new Get(
            openapi: new OpenApiOperation(
                summary: 'Journal',
                security: [
                    ['bearerAuth' =>  []],
                ]
            ),

            normalizationContext: [
                'groups' => ['read:Review']
            ],

            security: "is_granted('ROLE_SECRETARY')",
        ),
        new GetCollection(
            openapi: new OpenApiOperation(
                summary: 'All Journals',
                security: [
                    ['bearerAuth' =>  []],
                ]
            ),
            normalizationContext: [
                'groups' => ['read:Reviews']
            ],

            security: "is_granted('ROLE_EPIADMIN')",

        ),

        new Get(
            uriTemplate: AppConstants::APP_CONST['custom_operations']['uri_template'][AppConstants::STATS_DASHBOARD_ITEM],
            openapi: new OpenApiOperation(
                tags: [OpenApiFactory::OAF_TAGS['stats']],
                summary: "Dashboard",
                description: "",
            ),
            normalizationContext: [
                'groups' => [
                    AppConstants::APP_CONST['normalizationContext']['groups']['review']['item']['read'][0]
                ]

            ],
            output: DashboardOutput::class,
            name: AppConstants::APP_CONST['custom_operations']['items']['review'][0],
            provider: ReviewStatsDataProvider::class
        ),

        new Get(
            uriTemplate: AppConstants::APP_CONST['custom_operations']['uri_template'][AppConstants::STATS_NB_SUBMISSIONS_ITEM],
            openapi: new OpenApiOperation(
                tags: [OpenApiFactory::OAF_TAGS['stats']],
                summary: "Total number of submissions",
                description: "",
            ),
            normalizationContext: [
                'groups' => [
                    AppConstants::APP_CONST['normalizationContext']['groups']['review']['item']['read'][0]
                ]

            ],
            output: SubmissionOutput::class,
            name: AppConstants::APP_CONST['custom_operations']['items']['review'][1],
            provider: ReviewStatsDataProvider::class,
        ),

        new Get(
            uriTemplate: AppConstants::APP_CONST['custom_operations']['uri_template'][AppConstants::STATS_DELAY_SUBMISSION_ACCEPTANCE ],
            openapi: new OpenApiOperation(
                tags: [OpenApiFactory::OAF_TAGS['stats']],
                summary: "todo",
                description: "todo",
            ),
            normalizationContext: [
                'groups' => [
                    AppConstants::APP_CONST['normalizationContext']['groups']['review']['item']['read'][0]
                ]

            ],
            output: SubmissionAcceptanceDelayOutput::class,
            name: AppConstants::APP_CONST['custom_operations']['items']['review'][2],
            provider: ReviewStatsDataProvider::class,
        ),

        new Get(
            uriTemplate: AppConstants::APP_CONST['custom_operations']['uri_template'][AppConstants::STATS_DELAY_SUBMISSION_PUBLICATION],
            openapi: new OpenApiOperation(
                tags: [OpenApiFactory::OAF_TAGS['stats']],
                summary: "todo",
                description: "todo",
            ),
            normalizationContext: [
                'groups' => [
                    AppConstants::APP_CONST['normalizationContext']['groups']['review']['item']['read'][0]
                ]

            ],
            output: SubmissionPublicationDelayOutput::class,
            name: AppConstants::APP_CONST['custom_operations']['items']['review'][3],
            provider: ReviewStatsDataProvider::class,
        ),
        new Get(
            uriTemplate: AppConstants::APP_CONST['custom_operations']['uri_template'][AppConstants::STATS_NB_USERS ],
            openapi: new OpenApiOperation(
                tags: [OpenApiFactory::OAF_TAGS['stats']],
                summary: "todo",
                description: "todo",
            ),
            normalizationContext: [
                'groups' => [
                    AppConstants::APP_CONST['normalizationContext']['groups']['review']['item']['read'][0]
                ]

            ],
            output: UsersStatsOutput::class,
            name: AppConstants::APP_CONST['custom_operations']['items']['review'][4],
            provider: ReviewStatsDataProvider::class,
        ),
    ],

    denormalizationContext: ['groups' => ['write:Review']],
    openapi: new OpenApiOperation(

    ),
    paginationEnabled: true,
    paginationItemsPerPage: 10,
    paginationMaximumItemsPerPage: 30

)]
class Review
{
    /**
     * @var int
     *
     * @ORM\Column(name="RVID", type="integer", nullable=false, options={"unsigned"=true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    #[Groups(['read:Reviews', 'read:Review'])]
    #[ApiProperty(identifier: false)]
    private int $rvid;

    /**
     * @var string
     *
     * @ORM\Column(name="CODE", type="string", length=50, nullable=false)
     */
    #[Groups(['read:Reviews', 'read:Review'])]
    #[ApiProperty(identifier: true)]
    private string $code;

    /**
     * @var string
     *
     * @ORM\Column(name="NAME", type="string", length=2000, nullable=false)
     */
    #[Groups(['read:Reviews', 'read:Review'])]
    private string $name;

    /**
     * @var int Property viewable and writable only by users with ROLE_ADMIN
     *
     * @ORM\Column(name="STATUS", type="smallint", nullable=false, options={"unsigned"=true})
     */
    #[Groups(['read:Reviews'])]
    #[ApiProperty(security: "is_granted('ROLE_EPIADMIN')")]
    private int $status;

    /**
     * @var DateTimeInterface
     *
     * @ORM\Column(name="CREATION", type="datetime", nullable=false)
     */
    #[Groups(['read:Reviews'])]
    #[ApiProperty(security: "is_granted('ROLE_EPIADMIN')")]
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    private DateTimeInterface $creation;

    /**
     * @var int
     *
     * @ORM\Column(name="PIWIKID", type="integer", nullable=false, options={"unsigned"=true})
     */
    #[Groups(['read:Reviews'])]
    #[ApiProperty(security: "is_granted('ROLE_EPIADMIN')")]
    private int $piwikid;

    /**
     * @ORM\OneToMany(targetEntity=Papers::class, mappedBy="review")
     */

    #[Groups(['read:Review'])]
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
            $paper->setReview();
        }

        return $this;
    }
}
