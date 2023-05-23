<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * PaperMetadata
 *
 * @ORM\Table(name="PAPER_METADATA", indexes={@ORM\Index(name="DOCID", columns={"DOCID"}), @ORM\Index(name="LANG", columns={"LANG"}), @ORM\Index(name="METANAME", columns={"METANAME"})})
 * @ORM\Entity
 */
class PaperMetadata
{
    /**
     * @var int
     *
     * @ORM\Column(name="ID", type="integer", nullable=false, options={"unsigned"=true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var int
     *
     * @ORM\Column(name="DOCID", type="integer", nullable=false, options={"unsigned"=true})
     */
    private $docid;

    /**
     * @var string
     *
     * @ORM\Column(name="METANAME", type="string", length=255, nullable=false)
     */
    private $metaname;

    /**
     * @var string|null
     *
     * @ORM\Column(name="METAVALUE", type="text", length=65535, nullable=true)
     */
    private $metavalue;

    /**
     * @var string
     *
     * @ORM\Column(name="LANG", type="string", length=3, nullable=false)
     */
    private $lang;

    /**
     * @var int
     *
     * @ORM\Column(name="UID", type="integer", nullable=false, options={"unsigned"=true,"comment"="Par defaut UID = 0 : la méta est recupérée automatiquement. sinon : saisie manuelle, donc, on enregistre l'UID de l'utilisateur"})
     */
    private $uid = '0';

    /**
     * @var int
     *
     * @ORM\Column(name="SOURCEID", type="smallint", nullable=false, options={"unsigned"=true,"comment"="Provenance"})
     */
    private $sourceid;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getMetaname(): ?string
    {
        return $this->metaname;
    }

    public function setMetaname(string $metaname): self
    {
        $this->metaname = $metaname;

        return $this;
    }

    public function getMetavalue(): ?string
    {
        return $this->metavalue;
    }

    public function setMetavalue(?string $metavalue): self
    {
        $this->metavalue = $metavalue;

        return $this;
    }

    public function getLang(): ?string
    {
        return $this->lang;
    }

    public function setLang(string $lang): self
    {
        $this->lang = $lang;

        return $this;
    }

    public function getUid(): ?int
    {
        return $this->uid;
    }

    public function setUid(int $uid): self
    {
        $this->uid = $uid;

        return $this;
    }

    public function getSourceid(): ?int
    {
        return $this->sourceid;
    }

    public function setSourceid(int $sourceid): self
    {
        $this->sourceid = $sourceid;

        return $this;
    }


}
