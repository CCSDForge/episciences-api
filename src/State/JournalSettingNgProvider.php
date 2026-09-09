<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\JournalSettingNg;
use App\Entity\Review;
use App\Exception\ResourceNotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

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

        $journalRepo = $this->entityManager->getRepository(Review::class);
        $journal = $journalRepo->getJournalByIdentifier($code);

        if (!$journal) {
            throw new ResourceNotFoundException(sprintf('Oops! not found Journal %s', $code));
        }

        $repo = $this->entityManager->getRepository(JournalSettingNg::class);

        try {
            $json = $repo->getFrontSettings($journal->getRvid());

            if (!$json) {
                throw new ResourceNotFoundException(sprintf('Oops! No configuration found for {%s}', $code));
            }

        } catch (NonUniqueResultException $e) {
            $this->logger->critical($e->getMessage());
            throw new \RuntimeException('Oops! An internal error has occurred. Please try again.');
        }

        return new Response($json, Response::HTTP_OK, ['Content-Type' => 'application/json']);

    }
}
