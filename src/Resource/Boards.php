<?php

namespace App\Resource;

use Symfony\Component\Serializer\Attribute\Groups;

class Boards
{
    public function __construct(
        #[Groups(['read:Boards'])]
        private ?array $boards = null
    )
    {
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