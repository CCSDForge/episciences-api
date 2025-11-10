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

        if (
            $object instanceof Volume ||
            $object instanceof Paginator ||
            is_array($object)) {

            if ($object instanceof Volume) { // applied to Item
                $this->processVolume($object);


            } elseif ($object instanceof Paginator) { // applied to paginated Collection

                try {
                    foreach ($object->getIterator() as $currentObject) {

                        if ($currentObject instanceof Volume) {
                            $this->processVolume($currentObject);
                        }

                    }
                } catch (\Exception $e) {
                    $this->logger->error($e->getMessage());

                }

            } else { // applied to Collection without pagination

                foreach ($object as $currentObject) {

                    if ($currentObject instanceof Volume) {
                        $this->processVolume($currentObject);
                    }

                }
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::VIEW => ['processVolumePapersCollection', EventPriorities::PRE_SERIALIZE],];
    }

    private function processVolume(Volume $volume): void
    {
        $sortedPapers = $this->volumeRepository->fetchSortedPapers($volume->getVid());

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
