<?php

namespace App\Resource;

use Symfony\Component\Serializer\Attribute\Groups;

class Boards
{
    #[Groups(['read:Boards'])]
    private ?array $technicalBoard;
    #[Groups(['read:Boards'])]
    private ?array $editorialBoard;

    #[Groups(['read:Boards'])]
    private ?array $scientificBoard;
    #[Groups(['read:Boards'])]
    private ?array $formerBoard;

    /**
     * @param array|null $editorial_board
     * @param array|null $technical_board
     * @param array|null $scientific_board
     * @param array|null $former_board
     */
    public function __construct(array $editorial_board = null, ?array $technical_board = null, ?array $scientific_board = null, ?array $former_board = null)
    {
        $this->technicalBoard = $technical_board;
        $this->editorialBoard = $editorial_board;
        $this->scientificBoard = $scientific_board;
        $this->formerBoard = $former_board;
    }

    public function getEditorialBoard(): ?array
    {
        return $this->editorialBoard;
    }

    public function setEditorialBoard(?array $editorialBoard): self
    {
        $this->editorialBoard = $editorialBoard;
        return $this;
    }

    public function getScientificBoard(): ?array
    {
        return $this->scientificBoard;
    }

    public function setScientificBoard(?array $scientificBoard): self
    {
        $this->scientificBoard = $scientificBoard;
        return $this;
    }

    public function getFormerBoard(): ?array
    {
        return $this->formerBoard;
    }

    public function setFormerBoard(?array $formerBoard): self
    {
        $this->formerBoard = $formerBoard;
        return $this;
    }

    public function getTechnicalBoard(): ?array
    {
        return $this->technicalBoard;
    }

    public function setTechnicalBoard(?array $technicalBoard): self
    {
        $this->technicalBoard = $technicalBoard;
        return $this;
    }


}