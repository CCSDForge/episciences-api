<?php

namespace App\Controller;

use App\Entity\Main\User;
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
        $user = $this->security->getUser();
        return
            $user ?
                $this->
                doctrine->
                getRepository(User::class)->
                findOneBy(['username' => $user->getUserIdentifier()])
                : null;
    }
}