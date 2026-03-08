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
        return $this->getRepositories()[$repoId]->getName();

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
        /** @var \App\Entity\MetadataSources $repo */
        $repo = $this->getRepositories()[$repoId];
        return $repo->toArray();
    }

}