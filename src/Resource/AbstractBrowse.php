<?php

namespace App\Resource;

use App\Entity\Review;


abstract class AbstractBrowse
{
    private ?Review $journal = null;

    public function getJournal(): ?Review
    {
        return $this->journal;
    }

    public function setJournal(?Review $journal = null): self
    {
        $this->journal = $journal;
        return $this;
    }

}