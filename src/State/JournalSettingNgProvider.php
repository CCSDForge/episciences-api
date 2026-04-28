<?php

namespace App\State;

use ApiPlatform\Metadata\Exception\RuntimeException;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;

use App\Entity\JournalSettingNg;
use App\Entity\Review;

use App\Exception\ResourceNotFoundException;

use App\Repository\JournalSettingNgRepository;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;

/**
 * Front settings
 */
class JournalSettingNgProvider implements ProviderInterface
{

    public function __construct(protected EntityManagerInterface $entityManager, protected LoggerInterface $logger)
    {

    }

    /**
     * @param Operation $operation
     * @param array $uriVariables
     * @param array $context
     * @return object|array|object[]|null
     * @throws ResourceNotFoundException
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {

        $code = $context['filters']['code'] ?? null;

        if (!$code) {
            return null;
        }

        /** @var ReviewRepository $journalRepo */
        $journalRepo = $this->entityManager->getRepository(Review::class);
        $journal = $journalRepo->getJournalByIdentifier($code);

        if (!$journal) {
            throw new ResourceNotFoundException(sprintf('Oops! not found Journal %s', $code));
        }

        /** @var JournalSettingNgRepository $repo */

        $repo = $this->entityManager->getRepository(JournalSettingNg::class);

        try {
            $json = $repo->getFrontSettings($journal->getRvid());

            if (!$json) {
                throw new ResourceNotFoundException(sprintf('Oops! No configuration found for {%s}', $code));
            }

        } catch (NonUniqueResultException $e) {
            $this->logger->critical($e->getMessage());
            return new \RuntimeException('Oops! An internal error has occurred. Please try again.');
        }

        return new Response($json, Response::HTTP_OK, ['Content-Type' => 'json']);

    }
}
