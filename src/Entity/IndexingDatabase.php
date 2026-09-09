<?php

namespace App\Entity;

use App\Enum\IndexingDatabaseStatus;
use App\Repository\IndexingDatabaseRepository;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

#[ORM\Table(name: self::TABLE)]
#[ORM\Entity(repositoryClass: IndexingDatabaseRepository::class)]
#[ORM\Index( columns: ['status'],name: 'idx_indexing_db_status',)]
class IndexingDatabase
{
    public const string TABLE = 'indexing_database';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true])]
    #[Groups(['read:IndexingDatabase', 'read:IndexingDatabases'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups(['read:IndexingDatabase', 'read:IndexingDatabases'])]
    private string $name;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    #[Groups(['read:IndexingDatabase', 'read:IndexingDatabases'])]
    private ?string $url = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    #[Groups(['read:IndexingDatabase', 'read:IndexingDatabases'])]
    private ?string $logo = null;

    #[ORM\Column(type: Types::STRING, length: 255, enumType: IndexingDatabaseStatus::class)]
    private IndexingDatabaseStatus $status = IndexingDatabaseStatus::PENDING;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['read:IndexingDatabase', 'read:IndexingDatabases'])]
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    private ?DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['read:IndexingDatabase', 'read:IndexingDatabases'])]
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    private ?DateTimeInterface $updatedAt = null;

    #[ORM\Column(name: 'created_by', type: Types::INTEGER, nullable: true, options: ['unsigned' => true])]
    private ?int $createdBy = null;

    #[ORM\ManyToMany(targetEntity: Review::class, inversedBy: 'indexingDatabases')]
    #[ORM\JoinTable(name: 'review_indexing_database')]
    #[ORM\JoinColumn(name: 'indexing_database_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'rvid', referencedColumnName: 'RVID')]
    private Collection $reviews;

    public function __construct()
    {
        $this->reviews = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): self
    {
        $this->url = $url;
        return $this;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function setLogo(?string $logo): self
    {
        $this->logo = $logo;
        return $this;
    }

    public function getStatus(): IndexingDatabaseStatus
    {
        return $this->status;
    }

    public function setStatus(IndexingDatabaseStatus $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?int $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    /**
     * @return Collection<int, Review>
     */
    public function getReviews(): Collection
    {
        return $this->reviews;
    }

    public function addReview(Review $review): self
    {
        if (!$this->reviews->contains($review)) {
            $this->reviews->add($review);
        }
        return $this;
    }

    public function removeReview(Review $review): self
    {
        $this->reviews->removeElement($review);
        return $this;
    }
}
