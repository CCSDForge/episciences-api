<?php

namespace App\Controller;

use Symfony\Component\Security\Core\Security;

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