<?php


namespace App\Controller\Api;



use App\Entity\Main\Papers;
use App\Entity\Main\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\Security;

class PapersCreateController extends AbstractController
{
    /**
     * petite particularité
     * Le système de controller d'API platform n'utilise pas le système de controller de base de Symfony:
     * se sont simplement des classes qui vont êtres invocable.
     *
     *
     */


    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }


    public function __invoke(Papers $data)
    {
        /** @var User $author */
        $author = $this->security->getUser();
        $data->setAuthor($author);
        return $data;
    }

}