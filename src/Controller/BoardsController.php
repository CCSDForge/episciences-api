<?php

namespace App\Controller;

use App\Entity\Review;
use App\Entity\Section;
use App\Entity\User;
use App\Entity\UserRoles;
use App\Resource\Boards;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

class BoardsController extends AbstractController
{

    public const ROLES_TO_SHOWS = [
        UserRoles::EDITORIAL_BOARD,
        UserRoles::FORMER_MEMBER,
        UserRoles::TECHNICAL_BOARD,
        UserRoles::SCIENTIFIC_BOARD,
        UserRoles::ROLE_GUEST_EDITOR,
        User::ROLE_EDITOR,
        User::ROLE_EDITOR_IN_CHIEF,
        User::ROLE_SECRETARY,
    ];

    public function __invoke(EntityManagerInterface $entityManager, LoggerInterface $logger, Request $request = null): ?Boards
    {
        $boards = [];

        if ($request !== null) {
            $tags = [];

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
                $boardTags = $userRolesRepo->boardsUsersQuery($journal->getRvid())->getQuery()->getArrayResult();

                if (empty($boardTags)) {
                    return (new Boards())->setBoards();
                }

                $boardIdentifies = [];

                foreach ($boardTags as $boardTag) {
                    $tags[$boardTag['roleid']][] = $boardTag['uid'];
                    if (!in_array($boardTag['uid'], $boardIdentifies, true)) {
                        $boardIdentifies[] = $boardTag['uid'];
                    }
                }

                $result1 = $userRolesRepo->joinUserRolesQuery($journal->getRvid())->getQuery()->getArrayResult();

                foreach ($result1 as $current1) {

                    if (!$current1['user']) {
                        $logger->info(sprintf('empty user [UID = %s', $current1['uid']), [
                            'cause' => sprintf("L'identifiant a probablement été supprimé da la table %s, mais il est toujours présent dans la table %s", User::TABLE, UserRoles::TABLE),
                        ]);

                        continue;

                    }


                    if(in_array($current1['roleid'], self::ROLES_TO_SHOWS, true)){
                        $rolesByUid[$current1['uid']]['roles'][] = $current1['roleid'];
                    }

                    $rolesByUid[$current1['uid']]['user'] = $current1['user'];
                }


                try {
                    $assignedSections = $entityManager->getRepository(Section::class)->getAssignedSection($journal->getRvid(), $boardIdentifies);
                } catch (Exception | \JsonException  $e) {
                    $assignedSections = [];
                    $logger->critical($e->getMessage());

                }

                foreach ($rolesByUid as $current) {

                    $uid = $current['user']['uid'] ?? null;

                    if (
                        (isset($tags[UserRoles::EDITORIAL_BOARD]) && in_array($uid, $tags[UserRoles::EDITORIAL_BOARD], true)) ||
                        (isset($tags[UserRoles::TECHNICAL_BOARD]) && in_array($uid, $tags[UserRoles::TECHNICAL_BOARD], true)) ||
                        (isset($tags[UserRoles::SCIENTIFIC_BOARD]) && in_array($uid, $tags[UserRoles::SCIENTIFIC_BOARD], true)) ||
                        (isset($tags[UserRoles::FORMER_MEMBER]) && in_array($uid, $tags[UserRoles::FORMER_MEMBER], true))
                    ) {
                        $currentUser = $current['user'];
                        $user = new User();
                        $user
                            ->setUid($uid)
                            ->setUuid($currentUser['uuid'])
                            ->setLangueid($currentUser['langueid'])
                            ->setScreenName($currentUser['screenName'])
                            ->setRoles([$current['roles']])
                            ->setEmail($currentUser['email'])
                            ->setCiv($currentUser['civ'])
                            ->setOrcid($currentUser['orcid'])
                            ->setAdditionalProfileInformation($currentUser['additionalProfileInformation'])
                            ->setLastname($currentUser['lastname'])
                            ->setFirstname($currentUser['firstname'])
                            ->setAssignedSections($assignedSections[$uid] ?? null);
                        $boards[] = $user;
                    }
                }

            }

        }

        return (new Boards())->setBoards($boards);
    }
}