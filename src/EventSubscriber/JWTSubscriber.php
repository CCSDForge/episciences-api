<?php

namespace App\EventSubscriber;

use App\Entity\Review;
use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class JWTSubscriber implements EventSubscriberInterface
{

    private RequestStack $requestStack;
    private ManagerRegistry $doctrine;


    public function __construct(RequestStack $requestStack, ManagerRegistry $doctrine)
    {
        $this->requestStack = $requestStack;
        $this->doctrine = $doctrine;
    }

    /**
     * @param JWTCreatedEvent $event
     * @return void
     * @throws \JsonException
     */
    public function onLexikJwtAuthenticationOnJwtCreated(JWTCreatedEvent $event): void
    {

        $currentReview = null;
        $rvId = null;

        $request = $this->requestStack->getCurrentRequest();
        $postedContent = $request ? json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR) : [];

        $rvCode = $postedContent['code'] ?? null;

        if ($rvCode) {
            $currentReview = $this->doctrine->getRepository(Review::class)->findOneBy(['code' => $rvCode]);
        }

        if ($currentReview && $currentReview->getStatus()) {
            $rvId = $currentReview->getRvid();
        }

        $data = $event->getData();


        /** @var User $user */
        $user = $event->getUser();
        $data['uid'] = $user->getUid();
        $data['roles'] = $user->getRoles($rvId);
        $data['rvId'] = $rvId ? $currentReview->getRvid() : null;
        $event->setData($data);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'lexik_jwt_authentication.on_jwt_created' => 'onLexikJwtAuthenticationOnJwtCreated',
        ];
    }
}
