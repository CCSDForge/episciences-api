<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * WebsiteNavigation
 */
#[ORM\Entity]
#[ORM\Table(name: 'WEBSITE_NAVIGATION')]
class WebsiteNavigation
{
    #[ORM\Column(name: 'NAVIGATIONID', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private int $navigationid;

    #[ORM\Column(name: 'SID', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    private int $sid;

    #[ORM\Column(name: 'PAGEID', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    private int $pageid;

    #[ORM\Column(name: 'TYPE_PAGE', type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: false)]
    private string $typePage;

    #[ORM\Column(name: 'CONTROLLER', type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: false)]
    private string $controller;

    #[ORM\Column(name: 'ACTION', type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: false)]
    private string $action;

    #[ORM\Column(name: 'LABEL', type: \Doctrine\DBAL\Types\Types::STRING, length: 500, nullable: false)]
    private string $label;

    #[ORM\Column(name: 'PARENT_PAGEID', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    private int $parentPageid;

    #[ORM\Column(name: 'PARAMS', type: \Doctrine\DBAL\Types\Types::TEXT, length: 65535, nullable: false)]
    private string $params;

    public function getNavigationid(): ?int
    {
        return $this->navigationid;
    }

    public function getSid(): ?int
    {
        return $this->sid;
    }

    public function setSid(int $sid): self
    {
        $this->sid = $sid;

        return $this;
    }

    public function getPageid(): ?int
    {
        return $this->pageid;
    }

    public function setPageid(int $pageid): self
    {
        $this->pageid = $pageid;

        return $this;
    }

    public function getTypePage(): ?string
    {
        return $this->typePage;
    }

    public function setTypePage(string $typePage): self
    {
        $this->typePage = $typePage;

        return $this;
    }

    public function getController(): ?string
    {
        return $this->controller;
    }

    public function setController(string $controller): self
    {
        $this->controller = $controller;

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

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getParentPageid(): ?int
    {
        return $this->parentPageid;
    }

    public function setParentPageid(int $parentPageid): self
    {
        $this->parentPageid = $parentPageid;

        return $this;
    }

    public function getParams(): ?string
    {
        return $this->params;
    }

    public function setParams(string $params): self
    {
        $this->params = $params;

        return $this;
    }


}
