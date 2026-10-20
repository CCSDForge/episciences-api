<?php

namespace App\Controller;



use App\Entity\Paper;

use App\Entity\User;
use App\Exception\MissingRequestParameterException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class PapersController
{
    /**
     * The user is identified by its UUID: unlike the sequential UID, it cannot be guessed.
     */
    public const string UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    /**
     * Returns a response: a scalar controller result is rejected by the API Platform view listeners.
     * @throws MissingRequestParameterException
     */
    public function __invoke(EntityManagerInterface $entityManager, ?Request $request = null): JsonResponse
    {
        return new JsonResponse($this->isAllowed($entityManager, $request));
    }

    /**
     * @throws MissingRequestParameterException
     */
    public function isAllowed(EntityManagerInterface $entityManager, ?Request $request = null): bool
    {

        if ($request instanceof \Symfony\Component\HttpFoundation\Request) {

            if (!$request->query->has('documentId')) {
                throw (new MissingRequestParameterException())::new('documentId', 'Request query');
            }

            $documentId = (int)$request->query->get('documentId');

            $uuid = $request->attributes->get('uuid');

            if (is_string($uuid) && preg_match(self::UUID_PATTERN, $uuid) === 1) {
                $user = $entityManager->getRepository(User::class)->findOneBy(['uuid' => strtolower($uuid)]);
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