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
    #[ORM\Column(name: 'id', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private int $id;

    #[ORM\Column(name: 'name', type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: false)]
    private string $name;

    #[ORM\Column(name: 'type', type: \Doctrine\DBAL\Types\Types::STRING, length: 0, nullable: false)]
    private string $type;

    #[ORM\Column(name: 'status', type: \Doctrine\DBAL\Types\Types::BOOLEAN, nullable: false, options: ['default' => '1', 'comment' => 'enabled by default'])]
    private bool $status = true;

    #[ORM\Column(name: 'identifier', type: \Doctrine\DBAL\Types\Types::STRING, length: 50, nullable: true, options: ['comment' => 'OAI identifier'])]
    private ?string $identifier = null;

    #[ORM\Column(name: 'base_url', type: \Doctrine\DBAL\Types\Types::STRING, length: 100, nullable: true, options: ['comment' => 'OAI base url'])]
    private ?string $baseUrl = null;

    #[ORM\Column(name: 'doi_prefix', type: \Doctrine\DBAL\Types\Types::STRING, length: 10, nullable: false)]
    private string $doiPrefix;

    #[ORM\Column(name: 'api_url', type: \Doctrine\DBAL\Types\Types::STRING, length: 100, nullable: false)]
    private string $apiUrl;

    #[ORM\Column(name: 'doc_url', type: \Doctrine\DBAL\Types\Types::STRING, length: 150, nullable: false, options: ['comment' => "See the document's page on"])]
    private string $docUrl;

    #[ORM\Column(name: 'paper_url', type: \Doctrine\DBAL\Types\Types::STRING, length: 100, nullable: false, options: ['comment' => 'PDF'])]
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

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'type' => $this->getType(),
            'baseUrl' => $this->getBaseUrl(),
            'doiPrefix' => $this->getDoiPrefix(),
            'apiUrl' => $this->getApiUrl()
        ];
    }


}