<?php

namespace App\EventSubscriber;

use ApiPlatform\Doctrine\Orm\Paginator;
use ApiPlatform\Symfony\EventListener\EventPriorities;
use App\Entity\Volume;
use App\Repository\VolumeRepository;
use App\Security\Voter\PapersVoter;
use Doctrine\Common\Collections\ArrayCollection;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

readonly class VolumeSubscriber implements EventSubscriberInterface
{

    public function __construct(private AuthorizationCheckerInterface $authChecker, private VolumeRepository $volumeRepository, private LoggerInterface $logger)
    {

    }

    /**
     * La collection originale de papiers ne comprend que ceux du volume principal.
     * Cela permettra de résoudre le problème en retravaillant la collection avant la sérialisation
     * @param ViewEvent $event
     * @return void
     */
    public function processVolumePapersCollection(ViewEvent $event): void
    {
        $object = $event->getControllerResult();

        if ($object instanceof Volume) { // applied to Item
            $this->processVolumes([$object]);

        } elseif ($object instanceof Paginator) { // applied to paginated Collection

            try {
                $this->processVolumes(iterator_to_array($object->getIterator(), false));
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
            }

        } elseif (is_array($object)) { // applied to Collection without pagination
            $this->processVolumes($object);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::VIEW => ['processVolumePapersCollection', EventPriorities::PRE_SERIALIZE],];
    }

    /**
     * Fetches the papers of all the volumes in a single query (avoids one query per volume).
     */
    private function processVolumes(array $objects): void
    {
        /** @var Volume[] $volumes */
        $volumes = array_values(array_filter(
            $objects,
            static fn($object): bool => $object instanceof Volume && $object->getVid() !== null
        ));

        if ($volumes === []) {
            return;
        }

        $sortedPapers = $this->volumeRepository->fetchSortedPapersByVolumes(
            array_map(static fn(Volume $volume): int => $volume->getVid(), $volumes)
        );

        foreach ($volumes as $volume) {
            $this->processVolume($volume, $sortedPapers[$volume->getVid()] ?? []);
        }
    }

    private function processVolume(Volume $volume, array $sortedPapers): void
    {
        $privateCollection = $sortedPapers['privateCollection'] ?? new ArrayCollection();
        $publicCollection = $sortedPapers['publicCollection'] ?? new ArrayCollection();

        if ($this->authChecker->isGranted('ROLE_SECRETARY', $volume)) {
            $papersCollection = new ArrayCollection(array_merge($privateCollection->toArray(), $publicCollection->toArray()));
            $volume
                ->setPapers($papersCollection)
                ->getPapers();

        } else {
            $volume
                ->setPapers($publicCollection)
                ->getPapers();
        }
    }
}
