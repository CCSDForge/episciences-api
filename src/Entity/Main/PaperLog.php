<?php

namespace App\Entity\Main;

use Doctrine\ORM\Mapping as ORM;

/**
 * PaperLog
 *
 * @ORM\Table(name="PAPER_LOG", indexes={@ORM\Index(name="fk_T_PAPER_MODIF_T_PAPERS_idx", columns={"DOCID"}), @ORM\Index(name="fk_T_PAPER_MODIF_T_USER_idx", columns={"UID"})})
 * @ORM\Entity(repositoryClass="App\Repository\Main\PaperLogRepository")
 */
class PaperLog
{
    /**
     * @var int
     *
     * @ORM\Column(name="LOGID", type="integer", nullable=false, options={"unsigned"=true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $logid;

    /**
     * @var int
     *
     * @ORM\Column(name="PAPERID", type="integer", nullable=false, options={"unsigned"=true})
     */
    private $paperid;

    /**
     * @var int
     *
     * @ORM\Column(name="DOCID", type="integer", nullable=false, options={"unsigned"=true})
     */
    private $docid;

    /**
     * @var int
     *
     * @ORM\Column(name="UID", type="integer", nullable=false, options={"unsigned"=true})
     */
    private $uid;

    /**
     * @var int
     *
     * @ORM\Column(name="RVID", type="integer", nullable=false, options={"unsigned"=true})
     */
    private $rvid;

    /**
     * @var string
     *
     * @ORM\Column(name="ACTION", type="string", length=50, nullable=false)
     */
    private $action;

    /**
     * @var string|null
     *
     * @ORM\Column(name="DETAIL", type="text", length=65535, nullable=true)
     */
    private $detail;

    /**
     * @var string|null
     *
     * @ORM\Column(name="FILE", type="string", length=150, nullable=true)
     */
    private $file;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="DATE", type="datetime", nullable=false)
     */
    private $date;

    public function getLogid(): ?int
    {
        return $this->logid;
    }

    public function getPaperid(): ?int
    {
        return $this->paperid;
    }

    public function setPaperid(int $paperid): self
    {
        $this->paperid = $paperid;

        return $this;
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

    public function getUid(): ?int
    {
        return $this->uid;
    }

    public function setUid(int $uid): self
    {
        $this->uid = $uid;

        return $this;
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

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(string $action): self
    {
        $this->action = $action;

        return $this;
    }

    public function getDetail(): ?string
    {
        return $this->detail;
    }

    public function setDetail(?string $detail): self
    {
        $this->detail = $detail;

        return $this;
    }

    public function getFile(): ?string
    {
        return $this->file;
    }

    public function setFile(?string $file): self
    {
        $this->file = $file;

        return $this;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }


}
