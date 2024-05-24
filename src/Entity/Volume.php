<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\AppConstants;
use App\OpenApi\OpenApiFactory;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;


#[ORM\Table(name: self::TABLE)]
#[ORM\Index(columns: ['RVID'], name: 'FK_CONFID_idx')]
#[ORM\Entity]
#[ApiResource(
    operations: [

        new Get(
            openapi: new OpenApiOperation(
                tags: [OpenApiFactory::OAF_TAGS['sections_volumes']],
                summary: 'Consult a particular volume',
                security: [['bearerAuth' =>  []],]

            ),

            normalizationContext: [
                'groups' => [AppConstants::APP_CONST['normalizationContext']['groups']['volume']['item']['read'][0]],
            ],


        ),
        new GetCollection(
            openapi: new OpenApiOperation(
                tags: [OpenApiFactory::OAF_TAGS['sections_volumes']],
                summary: 'Volumes list',
                security: [['bearerAuth' =>  []],]

            ),
            normalizationContext: [
                'groups' => [AppConstants::APP_CONST['normalizationContext']['groups']['volume']['collection']['read'][0]],
            ],
        ),


    ]
)]
#[ApiFilter(SearchFilter::class, properties: ['rvid' => 'exact', 'vid' => 'exact'])]
class Volume
{
    public const TABLE = 'VOLUME';
    public const DEFAULT_URI_TEMPLATE = '/volumes{._format}';

   #[ORM\Column(name: 'VID', type: 'integer', nullable: false, options: ['unsigned' => true])]
   #[ORM\Id]
   #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private int $vid;


    #[ORM\Column(name: 'RVID', type: 'integer', nullable: false, options: ['unsigned' => true])]
    #[Groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['collection']['read'][0]
        ]

    )]
    private int $rvid;

    #[ORM\Column(nullable: true)]
    #[Groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['collection']['read'][0]
        ]

    )]
    private ?int $vol_year = null;

    #[ORM\Column(type: 'string', nullable: true)]
    #[Groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['collection']['read'][0]
        ]

    )]
    private ?string $vol_type = null;

    #[ORM\Column(type: 'string', nullable: true)]
    #[Groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['collection']['read'][0]
        ]

    )]
    private ?string $type = null;


   #[ORM\Column(name: 'POSITION', type: 'integer', nullable: false, options: ['unsigned' => true])]
    private int $position;


    #[ORM\Column(name: 'BIB_REFERENCE', type: 'string', length: 255, nullable: true, options: ['comment' => "Volume's bibliographical reference"])]
    #[Groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['collection']['read'][0]
        ]

    )]
    private string $bibReference;


    #[ORM\Column(name: 'titles', type: 'json', nullable: true)]
    #[Groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['collection']['read'][0]
        ]

    )]
    private ?array  $titles ;
    #[ORM\Column(name: 'descriptions', type: 'json', nullable: true)]
    #[Groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['collection']['read'][0]
        ]

    )]
    private  ?array $descriptions;

    #[ORM\OneToMany(mappedBy: 'volume', targetEntity: Paper::class)]
    #[Groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['collection']['read'][0],

        ]

    )]
    private Collection $papers;

    #[Groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['collection']['read'][0],

        ]

    )]

    #[ORM\OneToMany(mappedBy: 'volume', targetEntity: VolumeSetting::class)]
    #[ApiProperty(security: "is_granted('ROLE_SECRETARY')")]
    private Collection $settings;

    #[ORM\OneToMany(mappedBy: 'volume', targetEntity: VolumeProceeding::class)]
    #[Groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['collection']['read'][0],

        ]

    )]
    private Collection $settings_proceeding;

    #[Groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['collection']['read'][0]
        ]

    )]

    #[ORM\OneToMany(mappedBy: 'volume', targetEntity: VolumeMetadata::class, orphanRemoval: true)]
    private Collection $metadata;

    public function __construct()
    {
        $this->papers = new ArrayCollection();
        $this->settings = new ArrayCollection();
        $this->settings_proceeding = new ArrayCollection();
        $this->metadata = new ArrayCollection();
    }

    public function getVid(): ?int
    {
        return $this->vid;
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

    public function getBibReference(): ?string
    {
        return $this->bibReference;
    }

    public function setBibReference(?string $bibReference): self
    {
        $this->bibReference = $bibReference;

        return $this;
    }

    /**
     * @return array|null
     */
    public function getDescriptions(): ?array
    {
        return $this->descriptions;
    }

    /**
     * @param array $descriptions
     * @return Volume
     */
    public function setDescriptions(array $descriptions): self
    {
        $this->descriptions = $descriptions;
        return $this;
    }

    /**
     * @return array|null
     */
    public function getTitles(): ?array
    {
        return $this->titles ?? ['en' => 'volume_' . $this->getVid() . '_title'];
    }

    /**
     * @param array $titles
     * @return Volume
     */
    public function setTitles(array $titles): self
    {
        $this->titles = $titles;
        return $this;
    }



    /**
     * @return Collection<int, Paper>
     */

    public function getPapers(): Collection
    {
        return $this->papers;
    }

    public function addPaper(Paper $paper): self
    {
        if (!$this->papers->contains($paper)) {
            $this->papers->add($paper);
            $paper->setVolume($this);
        }

        return $this;
    }

    public function removePaper(Paper $paper): self
    {
        // set the owning side to null (unless already changed)
        if ($this->papers->removeElement($paper) && $paper->getVolume() === $this) {
            $paper->setVolume(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, VolumeSetting>
     */
    public function getSettings(): Collection
    {
        return $this->settings;
    }

    public function getSettingsProceeding(): Collection
    {
        return $this->settings_proceeding;
    }

    public function addSetting(VolumeSetting $setting): self
    {
        if (!$this->settings->contains($setting)) {
            $this->settings->add($setting);
            $setting->setVolume($this);
        }

        return $this;
    }


    public function addSettingProceeding(VolumeProceeding $setting): self
    {
        if (!$this->settings_proceeding->contains($setting)) {
            $this->settings_proceeding->add($setting);
            $setting->setVolume($this);
        }

        return $this;
    }

    public function removeSetting(VolumeSetting $setting): self
    {
        // set the owning side to null (unless already changed)
        if ($this->settings->removeElement($setting) && $setting->getVolume() === $this) {
            $setting->setVolume(null);
        }

        return $this;
    }

    public function removeSettingProceeding(VolumeProceeding $setting): self
    {
        // set the owning side to null (unless already changed)
        if ($this->settings_proceeding->removeElement($setting) && $setting->getVolume() === $this) {
            $setting->setVolume(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, VolumeMetadata>
     */
    public function getMetadata(): Collection
    {
        return $this->metadata;
    }

    public function addMetadata(VolumeMetadata $metadata): self
    {
        if (!$this->metadata->contains($metadata)) {
            $this->metadata->add($metadata);
            $metadata->setVolume($this);
        }

        return $this;
    }

    public function removeMetadata(VolumeMetadata $metadata): self
    {
        // set the owning side to null (unless already changed)
        if ($this->metadata->removeElement($metadata) && $metadata->getVolume() === $this) {
            $metadata->setVolume(null);
        }

        return $this;
    }

    public function getVolYear(): ?int
    {
        return $this->vol_year;
    }

    public function setVolYear(?int $vol_year): static
    {
        $this->vol_year = $vol_year;

        return $this;
    }

    public function getVolType(): ?string
    {
        return $this->vol_type;
    }

    public function setVolType(?array $vol_type): static
    {
        $this->vol_type = $vol_type;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?array $type): static
    {
        $this->type = $type;

        return $this;
    }


}
