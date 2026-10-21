<?php

declare(strict_types=1);

namespace App\Tests\Fixtures\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'test_unpaginated_limit_child')]
class LimitChild
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column]
        private int $id,
        #[ORM\ManyToOne(targetEntity: LimitParent::class, inversedBy: 'children')]
        #[ORM\JoinColumn(nullable: false)]
        private LimitParent $parent
    )
    {
        $parent->addChild($this);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getParent(): LimitParent
    {
        return $this->parent;
    }
}
