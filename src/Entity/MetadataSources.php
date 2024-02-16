<?php

namespace App\Entity;

use App\Repository\MetadataSourcesRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * MetadataSources
 */
#[ORM\Table(name: self::TABLE)]
#[ORM\Index(columns: ['type'], name: 'type')]
#[ORM\Entity(repositoryClass: MetadataSourcesRepository::class)]
class MetadataSources
{
    public const TABLE = 'metadata_sources';
    /**
     * @var int
     */
    #[ORM\Column(name: 'id', type: 'integer', nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private int $id;

    /**
     * @var string
     */
    #[ORM\Column(name: 'name', type: 'string', length: 255, nullable: false)]
    private string $name;

    /**
     * @var string
     */
    #[ORM\Column(name: 'type', type: 'string', length: 0, nullable: false)]
    private string $type;

    /**
     * @var bool
     */
    #[ORM\Column(name: 'status', type: 'boolean', nullable: false, options: ['default' => '1', 'comment' => 'enabled by default'])]
    private bool $status = true;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'identifier', type: 'string', length: 50, nullable: true, options: ['comment' => 'OAI identifier'])]
    private ?string $identifier;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'base_url', type: 'string', length: 100, nullable: true, options: ['comment' => 'OAI base url'])]
    private ?string $baseUrl;

    /**
     * @var string
     */
    #[ORM\Column(name: 'doi_prefix', type: 'string', length: 10, nullable: false)]
    private string $doiPrefix;

    /**
     * @var string
     */
    #[ORM\Column(name: 'api_url', type: 'string', length: 100, nullable: false)]
    private string $apiUrl;

    /**
     * @var string
     */
    #[ORM\Column(name: 'doc_url', type: 'string', length: 150, nullable: false, options: ['comment' => "See the document's page on"])]
    private string $docUrl;

    /**
     * @var string
     */
    #[ORM\Column(name: 'paper_url', type: 'string', length: 100, nullable: false, options: ['comment' => 'PDF'])]
    private string $paperUrl;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function isStatus(): bool
    {
        return $this->status;
    }

    public function setStatus(bool $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(?string $identifier): static
    {
        $this->identifier = $identifier;
        return $this;
    }

    public function getBaseUrl(): ?string
    {
        return $this->baseUrl;
    }

    public function setBaseUrl(?string $baseUrl): static
    {
        $this->baseUrl = $baseUrl;
        return $this;
    }

    public function getDoiPrefix(): string
    {
        return $this->doiPrefix;
    }

    public function setDoiPrefix(string $doiPrefix): static
    {
        $this->doiPrefix = $doiPrefix;
        return $this;
    }

    public function getApiUrl(): string
    {
        return $this->apiUrl;
    }

    public function setApiUrl(string $apiUrl): static
    {
        $this->apiUrl = $apiUrl;
        return $this;
    }

    public function getDocUrl(): string
    {
        return $this->docUrl;
    }

    public function setDocUrl(string $docUrl): static
    {
        $this->docUrl = $docUrl;
        return $this;
    }

    public function getPaperUrl(): string
    {
        return $this->paperUrl;
    }

    public function setPaperUrl(string $paperUrl): static
    {
        $this->paperUrl = $paperUrl;
        return $this;
    }


}