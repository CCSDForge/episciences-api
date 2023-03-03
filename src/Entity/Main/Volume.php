<?php

namespace App\Entity\Main;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\AppConstants;
use App\Repository\Main\VolumeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Table(name: self::TABLE)]
#[ORM\Index(columns: ['RVID'], name: 'FK_CONFID_idx')]
#[ORM\Entity(repositoryClass: VolumeRepository::class)]
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

    #[ORM\Column(name: 'doi', type: 'string', nullable: true)]
    #[Groups(
        [
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['item']['read'][0],
            AppConstants::APP_CONST['normalizationContext']['groups']['volume']['collection']['read'][0]
        ]

    )]
    private string $doi;

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
     * @return string
     */
    public function getDoi(): string
    {
        return $this->doi;
    }

    /**
     * @param string $doi
     * @return Volume
     */
    public function setDoi(string $doi): self
    {
        $this->doi = $doi;
        return $this;
    }


}
