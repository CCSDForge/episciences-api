<?php

namespace App\Entity\Main;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use Doctrine\ORM\Mapping as ORM;

/**
 * Volume
 *
 * @ORM\Table(name="VOLUME", indexes={@ORM\Index(name="FK_CONFID_idx", columns={"RVID"})})
 * @ORM\Entity
 */
#[ApiResource(
    operations: [

        new Get(
            openapi: new OpenApiOperation(
                summary: 'todo',

            ),

            normalizationContext: [
                'groups' => ['read:Volume']
            ],


        ),
        new GetCollection(
            openapi: new OpenApiOperation(
                summary: 'Volumes list',

            ),
            normalizationContext: [
                'groups' => ['read:Volumes']
            ],
        ),


    ]
)]
class Volume
{
    /**
     * @var int
     *
     * @ORM\Column(name="VID", type="integer", nullable=false, options={"unsigned"=true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $vid;

    /**
     * @var int
     *
     * @ORM\Column(name="RVID", type="integer", nullable=false, options={"unsigned"=true})
     */
    private $rvid;

    /**
     * @var int
     *
     * @ORM\Column(name="POSITION", type="integer", nullable=false, options={"unsigned"=true})
     */
    private $position;

    /**
     * @var string|null
     *
     * @ORM\Column(name="BIB_REFERENCE", type="string", length=255, nullable=true, options={"comment"="Volume's bibliographical reference"})
     */
    private $bibReference;

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


}
