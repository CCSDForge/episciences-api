<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\AppConstants;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Table(name: self::TABLE)]
#[ORM\Index(columns: ['RVID'], name: 'FK_CONFID_idx')]
#[ORM\Entity()]
#[ApiResource(
    operations: [

        new Get(
            openapi: new OpenApiOperation(
                summary: 'Consult a particular volume',

            ),

            normalizationContext: [
                'groups' => [AppConstants::APP_CONST['normalizationContext']['groups']['volume']['item']['read'][0]],
            ],


        ),
        new GetCollection(
            openapi: new OpenApiOperation(
                summary: 'Volumes list',

            ),
            normalizationContext: [
                'groups' => [AppConstants::APP_CONST['normalizationContext']['groups']['volume']['collection']['read'][0]],
            ],
        ),


    ]
)]
class Volume
{
    public const TABLE = 'VOLUME';

   #[ORM\Column(name: 'VID', type: 'integer', nullable: false, options: ['unsigned' => true])]
   #[ORM\Id]
   #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private $vid;


    #[ORM\Column(name: 'RVID', type: 'integer', nullable: false, options: ['unsigned' => true])]
    #[Groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['collection']['read'][0]
        ]

    )]
    private $rvid;


   #[ORM\Column(name: 'POSITION', type: 'integer', nullable: false, options: ['unsigned' => true])]
    private $position;


    #[ORM\Column(name: 'BIB_REFERENCE', type: 'string', length: 255, nullable: true, options: ['comment' => "Volume's bibliographical reference"])]
    #[Groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['collection']['read'][0]
        ]

    )]
    private $bibReference;


    #[ORM\Column(name: 'titles', type: 'json', nullable: true)]
    #[Groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['collection']['read'][0]
        ]

    )]
    private array  $titles ;
    #[ORM\Column(name: 'descriptions', type: 'json', nullable: true)]
    #[Groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['collection']['read'][0]
        ]

    )]
    private  array $descriptions;

    #[ORM\OneToMany(mappedBy: 'volume', targetEntity: Papers::class)]
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
    private Collection $settings;

    public function __construct()
    {
        $this->papers = new ArrayCollection();
        $this->settings = new ArrayCollection();
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
     * @return array
     */
    public function getDescriptions(): array
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
     * @return array
     */
    public function getTitles(): array
    {
        return $this->titles;
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
     * @return Collection<int, Papers>
     */
    public function getPapers(): Collection
    {
        return $this->papers;
    }

    public function addPaper(Papers $paper): self
    {
        if (!$this->papers->contains($paper)) {
            $this->papers->add($paper);
            $paper->setVolume($this);
        }

        return $this;
    }

    public function removePaper(Papers $paper): self
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

    public function addSetting(VolumeSetting $setting): self
    {
        if (!$this->settings->contains($setting)) {
            $this->settings->add($setting);
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


}
