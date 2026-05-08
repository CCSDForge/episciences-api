<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\SecurityBundle\Security;

class MeController
{

    public function __construct(private readonly Security $security, private readonly ManagerRegistry $doctrine)
    {
    }

    public function __invoke()
    {
        // User from payload: @see App\EventSubscriber::onLexikJwtAuthenticationOnJwtCreated
        /** @var User $user */
        $user = $this->security->getUser();

        if ($user) {
            $refreshedUser  = $this->doctrine->
            getRepository(User::class)->
            findOneBy(['uid' => $user->getUid()]);

            if ($refreshedUser) {
                $refreshedUser->setRoles($refreshedUser->getRoles($user->getCurrentJournalID()));
                $refreshedUser->setCurrentJournalID($user->getCurrentJournalID());
                return $refreshedUser;
            }

        }

        return null;
    }
}