<?php

namespace App\Controller;

use Symfony\Bundle\SecurityBundle\Security;

class MeController
{

    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;

    }

    public function __invoke()
    {
        return $this->security->getUser();
    }

}