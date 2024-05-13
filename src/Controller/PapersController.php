<?php

namespace App\Controller;



use App\Entity\Paper;

use App\Entity\User;
use App\Exception\MissingRequestParameterException;
use Doctrine\ORM\EntityManagerInterface;
use JetBrains\PhpStorm\NoReturn;
use Symfony\Component\HttpFoundation\Request;

class PapersController
{

    /**
     * @param EntityManagerInterface $entityManager
     * @param Request|null $request
     * @return bool
     * @throws MissingRequestParameterException
     */
    #[NoReturn]
    public function __invoke(EntityManagerInterface $entityManager, Request $request = null): bool
    {

        if ($request !== null) {

            if (!$request->query->has('documentId')) {
                throw (new MissingRequestParameterException())::new('documentId', 'Request query');
            }

            $documentId = (int)$request->query->get('documentId');

            $userId = (int)$request->get('uid');

            if ($userId) {
                $user = $entityManager->getRepository(User::class)->findOneBy(['uid' => $userId]);
                /** @var Paper $currentPaper */
                $currentPaper = $entityManager->getRepository(Paper::class)->findOneBy(['docid' => $documentId]);

                if (!$user || !$currentPaper) {
                    return false;
                }

                $rvId = $currentPaper->getRvid();

                return
                    $user->hasRole(User::ROLE_SECRETARY, $rvId) ||
                    $user->hasRole(User::ROLE_ADMINISTRATOR, $rvId) ||
                    $user->hasRole(User::ROLE_EDITOR_IN_CHIEF, $rvId) ||
                    $user->hasRole(USER::ROLE_ROOT, $rvId) ||

                    in_array($user->getUid(), $currentPaper->getUsersAllowedToEditPaperCitations(), true);

            }


        }


        return false;

    }

}