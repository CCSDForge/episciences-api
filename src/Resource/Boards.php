<?php

namespace App\Resource;

use Symfony\Component\Serializer\Attribute\Groups;

class Boards
{
    #[Groups(['read:Boards'])]
    private ?array $boards;

    public function __construct(array $boards = null)
    {
        $this->boards= $boards;
    }

    public function getBoards(): ?array
    {
        return $this->boards;
    }

    public function setBoards(?array $boards = null): self
    {
        $this->boards = $boards;
        return $this;
    }


}