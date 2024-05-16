<?php

namespace App\Controller;

use App\Entity\Review;
use App\Entity\User;
use App\Entity\UserRoles;
use App\Resource\Boards;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

class BoardsController extends AbstractController
{


    public function __invoke(EntityManagerInterface $entityManager, LoggerInterface $logger, Request $request = null): ?Boards
    {

        if ($request !== null) {
            $tags = [];
            $boards = [];
            $rolesByUid = [];
            $code = $request->get('code');

            if ($code) {
                $result = $entityManager->getRepository(Review::class)->findBy(['code' => $code]);
                /** @var  Review $journal */
                $journal = $result[array_key_first($result)] ?? null;

                if (!$journal) {
                    return null;
                }

                $userRolesRepo = $entityManager->getRepository(UserRoles::class);
                $boardTags = $userRolesRepo->boardsTagsQuery($journal->getRvid())->getQuery()->getArrayResult();

                foreach ($boardTags as $boardTag) {
                    $tags[$boardTag['roleid']][] = $boardTag['uid'];
                }

                $result1 = $userRolesRepo->boardsQuery($journal->getRvid())->getQuery()->getArrayResult();

                foreach ($result1 as $current1) {

                    if (!$current1['user']) {
                        $logger->info(sprintf('empty user [UID = %s', $current1['uid']), [
                            'cause' => sprintf("L'identifiant a probablement été supprimé da la table %s, mais il est toujours présent dans la table %s", User::TABLE, UserRoles::TABLE),
                        ]);

                        continue;

                    }
                    $rolesByUid[$current1['uid']]['roles'][] = $current1['roleid'];
                    $rolesByUid[$current1['uid']]['user'] = $current1['user'];
                }

                foreach ($rolesByUid as $current) {

                    $uid = $current['user']['uid'] ?? null;
                    $currentUser = $current['user'];
                    $user = new User();
                    $user->setUid($uid)
                        ->setLangueid($currentUser['langueid'])
                        ->setScreenName($currentUser['screenName'])
                        ->setRoles([$current['roles']])
                        ->setEmail($currentUser['email'])
                        ->setCiv($currentUser['civ'])
                        ->setOrcid($currentUser['orcid'])
                        ->setAdditionalProfileInformation($currentUser['additionalProfileInformation'])
                        ->setLastname($currentUser['lastname'])
                        ->setFirstname($currentUser['firstname']);

                    if (
                        (isset($tags[UserRoles::EDITORIAL_BOARD]) && in_array($uid, $tags[UserRoles::EDITORIAL_BOARD], true)) ||
                        (isset($tags[UserRoles::TECHNICAL_BOARD]) && in_array($uid, $tags[UserRoles::TECHNICAL_BOARD], true)) ||
                        (isset($tags[UserRoles::SCIENTIFIC_BOARD]) && in_array($uid, $tags[UserRoles::SCIENTIFIC_BOARD], true)) ||
                        (isset($tags[UserRoles::FORMER_MEMBER]) && in_array($uid, $tags[UserRoles::FORMER_MEMBER], true))
                    ) {
                        $boards[] = $user;
                    }
                }

            }

        }

        return (new Boards())->setBoards($boards);
    }
}