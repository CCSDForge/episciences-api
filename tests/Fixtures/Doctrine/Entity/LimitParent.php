<?php

declare(strict_types=1);

namespace App\Tests\Fixtures\Doctrine\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Root entity with a to-many association, for UnpaginatedCollectionLimitExtensionDatabaseTest.
 */
#[ORM\Entity]
#[ORM\Table(name: 'test_unpaginated_limit_parent')]
class LimitParent
{
    /** @var Collection<int, LimitChild> */
    #[ORM\OneToMany(targetEntity: LimitChild::class, mappedBy: 'parent', cascade: ['persist'])]
    private Collection $children;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column]
        private int $id
    )
    {
        $this->children = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return Collection<int, LimitChild>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(LimitChild $child): self
    {
        $this->children->add($child);

        return $this;
    }
}
