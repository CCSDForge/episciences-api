<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Paper;
use App\Repository\PaperConflictsRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::postLoad)]
class PaperConflictsSubscriber
{
    public function __construct(private readonly PaperConflictsRepository $paperConflictsRepository)
    {
    }

    public function postLoad(Paper $paper): void
    {
        if ($paper->getPaperid() !== null && $paper->getConflicts()->isEmpty()) {
            $paper->setConflicts($this->paperConflictsRepository->findByPaperId($paper->getPaperid()));
        }
    }
}
