<?php

namespace App\Service;

use App\Repository\MetadataSourcesRepository;


class MetadataSources
{

    private MetadataSourcesRepository $metadataRepository;
    private array $repositories = [];


    public function __construct(MetadataSourcesRepository $metadataRepository)
    {
        $this->metadataRepository = $metadataRepository;

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


}