<?php

namespace App\Controller;

use App\Entity\News;
use App\Entity\Review;
use App\Entity\Volume;
use App\Resource\Range;
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

        $journal = $this->entityManager->getRepository(Review::class)->getJournalByIdentifier($code);

        $identifier = null;

        if ($journal) {
            if ($this->resourceName === News::class) {
                $identifier = $journal->getCode();
            } elseif ($this->resourceName === Volume::class) {
                $identifier = $journal->getRvid();
            }
        }
        $result = $this->entityManager->getRepository($this->resourceName)->getRange($identifier);
        return (new Range())->setValues($result);
    }

    #[Required]
    public function setEntityManager(EntityManagerInterface $entityManager): EntityManagerInterface
    {
        $this->entityManager = $entityManager;
        return $this->entityManager;
    }

}