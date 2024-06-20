<?php

namespace App\Controller;

use App\Entity\News;
use App\Entity\Review;
use App\Entity\Section;
use App\Entity\Volume;
use App\Resource\Range;
use App\Resource\RangeType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\Service\Attribute\Required;

abstract class RangeController extends AbstractController
{

    protected string $resourceName;
    private EntityManagerInterface $entityManager;

    public function setResourceName(string $resourceName): void
    {
        $this->resourceName = $resourceName;
    }

    public function getResult(Request $request): Range
    {
        $code = $request->get('rvcode');
        $identifier = null;

        $journal = $this->entityManager->getRepository(Review::class)->getJournalByIdentifier($code);

        if ($journal) {
            if ($this->resourceName === News::class) {
                $identifier = $journal->getCode();
            } elseif ($this->resourceName === Volume::class) {
                $identifier = $journal->getRvid();
            }
        }

        $repo = $this->entityManager->getRepository($this->resourceName);

        if ($this->resourceName === News::class) {
            return (new Range())->setYears($repo->getRange($identifier));
        }

        $oRangeType = new RangeType();

        if ($this->resourceName === Volume::class) {
            $oRangeType
                ->setTypes($this->entityManager->getRepository($this->resourceName)->getTypes($identifier))
                ->setYears($repo->getRange($identifier));
        }

        return $oRangeType;
    }

    #[Required]
    public function setEntityManager(EntityManagerInterface $entityManager): EntityManagerInterface
    {
        $this->entityManager = $entityManager;
        return $this->entityManager;
    }

}