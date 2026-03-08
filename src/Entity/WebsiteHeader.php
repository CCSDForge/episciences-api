<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * WebsiteHeader
 */
#[ORM\Entity]
#[ORM\Table(name: 'WEBSITE_HEADER')]
class WebsiteHeader
{
    #[ORM\Column(name: 'LOGOID', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private int $logoid;

    #[ORM\Column(name: 'RVID', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private int $rvid;

    #[ORM\Column(name: 'TYPE', type: \Doctrine\DBAL\Types\Types::STRING, length: 0, nullable: false)]
    private string $type;

    #[ORM\Column(name: 'IMG', type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: false)]
    private string $img;

    #[ORM\Column(name: 'IMG_WIDTH', type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: false)]
    private string $imgWidth;

    #[ORM\Column(name: 'IMG_HEIGHT', type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: false)]
    private string $imgHeight;

    #[ORM\Column(name: 'IMG_HREF', type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: false)]
    private string $imgHref;

    #[ORM\Column(name: 'IMG_ALT', type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: false)]
    private string $imgAlt;

    #[ORM\Column(name: 'TEXT', type: \Doctrine\DBAL\Types\Types::STRING, length: 1000, nullable: false)]
    private string $text;

    #[ORM\Column(name: 'TEXT_CLASS', type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: false)]
    private string $textClass;

    #[ORM\Column(name: 'TEXT_STYLE', type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: false)]
    private string $textStyle;

    #[ORM\Column(name: 'ALIGN', type: \Doctrine\DBAL\Types\Types::STRING, length: 10, nullable: false)]
    private string $align;

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
