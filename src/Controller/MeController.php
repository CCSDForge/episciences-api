<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\SecurityBundle\Security;

class MeController
{

    private Security $security;
    private ManagerRegistry $doctrine;

    public function __construct(Security $security, ManagerRegistry $doctrine)
    {
        $this->security = $security;
        $this->doctrine = $doctrine;

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
                $refreshedUser->setRoles($refreshedUser->getRoles($user->rvId));
                return $refreshedUser;
            }

        }

        return null;
    }
}