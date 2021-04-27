<?php

namespace App\Entity\Main;

use Doctrine\ORM\Mapping as ORM;

/**
 * WebsiteHeader
 *
 * @ORM\Table(name="WEBSITE_HEADER")
 * @ORM\Entity
 */
class WebsiteHeader
{
    /**
     * @var int
     *
     * @ORM\Column(name="LOGOID", type="integer", nullable=false, options={"unsigned"=true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $logoid;

    /**
     * @var int
     *
     * @ORM\Column(name="RVID", type="integer", nullable=false, options={"unsigned"=true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $rvid;

    /**
     * @var string
     *
     * @ORM\Column(name="TYPE", type="string", length=0, nullable=false)
     */
    private $type;

    /**
     * @var string
     *
     * @ORM\Column(name="IMG", type="string", length=255, nullable=false)
     */
    private $img;

    /**
     * @var string
     *
     * @ORM\Column(name="IMG_WIDTH", type="string", length=255, nullable=false)
     */
    private $imgWidth;

    /**
     * @var string
     *
     * @ORM\Column(name="IMG_HEIGHT", type="string", length=255, nullable=false)
     */
    private $imgHeight;

    /**
     * @var string
     *
     * @ORM\Column(name="IMG_HREF", type="string", length=255, nullable=false)
     */
    private $imgHref;

    /**
     * @var string
     *
     * @ORM\Column(name="IMG_ALT", type="string", length=255, nullable=false)
     */
    private $imgAlt;

    /**
     * @var string
     *
     * @ORM\Column(name="TEXT", type="string", length=1000, nullable=false)
     */
    private $text;

    /**
     * @var string
     *
     * @ORM\Column(name="TEXT_CLASS", type="string", length=255, nullable=false)
     */
    private $textClass;

    /**
     * @var string
     *
     * @ORM\Column(name="TEXT_STYLE", type="string", length=255, nullable=false)
     */
    private $textStyle;

    /**
     * @var string
     *
     * @ORM\Column(name="ALIGN", type="string", length=10, nullable=false)
     */
    private $align;

    public function getLogoid(): ?int
    {
        return $this->logoid;
    }

    public function getRvid(): ?int
    {
        return $this->rvid;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getImg(): ?string
    {
        return $this->img;
    }

    public function setImg(string $img): self
    {
        $this->img = $img;

        return $this;
    }

    public function getImgWidth(): ?string
    {
        return $this->imgWidth;
    }

    public function setImgWidth(string $imgWidth): self
    {
        $this->imgWidth = $imgWidth;

        return $this;
    }

    public function getImgHeight(): ?string
    {
        return $this->imgHeight;
    }

    public function setImgHeight(string $imgHeight): self
    {
        $this->imgHeight = $imgHeight;

        return $this;
    }

    public function getImgHref(): ?string
    {
        return $this->imgHref;
    }

    public function setImgHref(string $imgHref): self
    {
        $this->imgHref = $imgHref;

        return $this;
    }

    public function getImgAlt(): ?string
    {
        return $this->imgAlt;
    }

    public function setImgAlt(string $imgAlt): self
    {
        $this->imgAlt = $imgAlt;

        return $this;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(string $text): self
    {
        $this->text = $text;

        return $this;
    }

    public function getTextClass(): ?string
    {
        return $this->textClass;
    }

    public function setTextClass(string $textClass): self
    {
        $this->textClass = $textClass;

        return $this;
    }

    public function getTextStyle(): ?string
    {
        return $this->textStyle;
    }

    public function setTextStyle(string $textStyle): self
    {
        $this->textStyle = $textStyle;

        return $this;
    }

    public function getAlign(): ?string
    {
        return $this->align;
    }

    public function setAlign(string $align): self
    {
        $this->align = $align;

        return $this;
    }


}
