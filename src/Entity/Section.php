<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\AppConstants;
use App\OpenApi\OpenApiFactory;
use App\Repository\SectionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Section
 */
#[ORM\Table(name: self::TABLE)]
#[ORM\Index(columns: ['RVID'], name: 'FK_CONFID_idx')]
#[ORM\Index(columns: ['POSITION'], name: 'POSITION')]
#[ORM\Entity(repositoryClass: SectionRepository::class)]
#[ApiResource(
    operations: [

        new Get(
            openapi: new OpenApiOperation(
                tags: [OpenApiFactory::OAF_TAGS['sections_volumes']],
                summary: 'Consult a particular Section',
                security: [['bearerAuth' => []],]

            ),

            normalizationContext: [
                'groups' => [AppConstants::APP_CONST['normalizationContext']['groups']['section']['item']['read'][0]],
            ],


        ),
        new GetCollection(
            openapi: new OpenApiOperation(
                tags: [OpenApiFactory::OAF_TAGS['sections_volumes']],
                summary: 'Sections list',
                security: [['bearerAuth' => []],]

            ),
            order: ['rvid' => AppConstants::ORDER_DESC, 'sid' => AppConstants::ORDER_DESC],
            normalizationContext: [
                'groups' => [AppConstants::APP_CONST['normalizationContext']['groups']['section']['collection']['read'][0]],
            ],

        ),


    ]
)]
class Section
{
    public const TABLE = 'SECTION';
    public const DEFAULT_URI_TEMPLATE = '/sections{._format}';
    /**
     * @var int
     */
    #[ORM\Column(name: 'SID', type: 'integer', nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[Groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['section']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['section']['collection']['read'][0],
            'read:Boards'
        ]

    )]
    private int $sid;

    /**
     * @var int
     */
    #[ORM\Column(name: 'RVID', type: 'integer', nullable: false, options: ['unsigned' => true])]
    #[Groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['section']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['section']['collection']['read'][0],
        ]

    )]
    private int $rvid;

    /**
     * @var int
     */
    #[ORM\Column(name: 'POSITION', type: 'integer', nullable: false, options: ['unsigned' => true])]
    private int $position;

    #[ORM\Column(name: 'titles', type: 'json', nullable: true)]
    #[Groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['section']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['section']['collection']['read'][0],
            'read:Boards'
        ]

    )]
    private ?array $titles;

    #[ORM\Column(name: 'descriptions', type: 'json', nullable: true)]
    #[Groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['section']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['section']['collection']['read'][0]
        ]

    )]
    private ?array $descriptions;

    #[ORM\OneToMany(mappedBy: 'section', targetEntity: Paper::class)]
    #[Groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['section']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['section']['collection']['read'][0],

        ]

    )]
    private Collection $papers;
    #[ORM\OneToMany(mappedBy: 'section', targetEntity: SectionSetting::class)]
    #[ApiProperty(security: "is_granted('ROLE_SECRETARY')")]
    #[Groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['section']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['section']['collection']['read'][0],

        ]

    )]
    private Collection $settings;

    public function __construct()
    {
        $this->papers = new ArrayCollection();
        $this->settings = new ArrayCollection();
    }


    public function getRvid(): ?int
    {
        return $this->rvid;
    }

    public function setRvid(int $rvid): self
    {
        $this->rvid = $rvid;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getTitles(): ?array
    {
        return $this->titles;
    }

    public function setTitles(?array $titles = null): self
    {
        $this->titles = $titles;
        return $this;
    }

    public function getDescriptions(): ?array
    {
        return $this->descriptions;
    }

    public function setDescriptions(?array $descriptions = null): self
    {
        $this->descriptions = $descriptions;
        return $this;
    }

    public function addPaper(Paper $paper): self
    {
        if (!$this->papers->contains($paper)) {
            $this->papers->add($paper);
            $paper->setSection($this);
        }

        return $this;
    }

    public function removePaper(Paper $paper): self
    {
        // set the owning side to null (unless already changed)
        if ($this->papers->removeElement($paper) && $paper->getSection() === $this) {
            $paper->setSection(null);
        }

        return $this;
    }

    public function getPapers(): Collection
    {
        return $this->papers;
    }

    /**
     * @return Collection<int, VolumeSetting>
     */
    public function getSettings(): Collection
    {
        return $this->settings;
    }

    public function addSetting(SectionSetting $setting): self
    {
        if (!$this->settings->contains($setting)) {
            $this->settings->add($setting);
            $setting->setSection($this);
        }

        return $this;
    }

    public function removeSetting(SectionSetting $setting): self
    {
        // set the owning side to null (unless already changed)
        if ($this->settings->removeElement($setting) && $setting->getSection() === $this) {
            $setting->setSection(null);
        }

        return $this;
    }

    public function getSid(): int
    {
        return $this->sid;
    }

    public function setSid(int $sid): self
    {
        $this->sid = $sid;
        return $this;
    }


}
