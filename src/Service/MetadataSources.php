<?php

namespace App\Service;

use App\Repository\MetadataSourcesRepository;


class MetadataSources
{

    private array $repositories = [];


    public function __construct(private readonly MetadataSourcesRepository $metadataRepository)
    {
    }

    public function loadRepositories(): void
    {
        $this->repositories = $this->metadataRepository->findAll();
    }

    public function getLabel(int $repoId): string
    {
        $repositories = $this->getRepositories();

        if (!isset($repositories[$repoId])) {
            throw new \InvalidArgumentException(sprintf('Repository with id "%d" not found.', $repoId));
        }

        return $repositories[$repoId]->getName();
    }

    public function getRepositories(): array
    {

        if (!$this->repositories) {
            $this->loadRepositories();
        }

        return $this->repositories;

    }

    public function repositoryToArray(int $repoId): array
    {
        $repositories = $this->getRepositories();

        if (!isset($repositories[$repoId])) {
            throw new \InvalidArgumentException(sprintf('Repository with id "%d" not found.', $repoId));
        }

        /** @var \App\Entity\MetadataSources $repo */
        $repo = $repositories[$repoId];
        return $repo->toArray();
    }

}