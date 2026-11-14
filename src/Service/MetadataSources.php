<?php

namespace App\Service;

use App\Repository\MetadataSourcesRepository;


class MetadataSources
{
    public const TYPE_PAPERS_REPOSITORY = 'repository';
    public const TYPE_METADATA_REPOSITORY = 'metadataRepository';
    public const TYPE_DATAVERSE = 'dataverse';
    public const TYPE_DSPACE = 'dspace';
    public const TYPE_USER = 'user';

    private array $repositories = [];


    public function __construct(private readonly MetadataSourcesRepository $metadataRepository)
    {
    }

    public function loadRepositories(): void
    {
        // Index the repositories by their id so they can be looked up directly.
        $repositories = [];
        foreach ($this->metadataRepository->findAll() as $repository) {
            $repositories[$repository->getId()] = $repository;
        }
        $this->repositories = $repositories;
    }

    public function getLabel(int $repoId): string
    {
        $repositories = $this->getRepositories();

        if (!isset($repositories[$repoId])) {
            return '';
        }

        return $repositories[$repoId]->getName();
    }

    /**
     * The repositories that can be used as paper/import sources (aligned with the GPL reference
     * getRepositories()): "dspace", "dataverse" and "repository" types, excluding "Software Heritage".
     *
     * @return array<int, \App\Entity\MetadataSources>
     */
    public function getRepositories(): array
    {

        if (!$this->repositories) {
            $this->loadRepositories();
        }

        return array_filter($this->repositories, static function (\App\Entity\MetadataSources $repository): bool {
            return $repository->getName() !== 'Software Heritage' &&
                in_array($repository->getType(), [
                    self::TYPE_DSPACE,
                    self::TYPE_DATAVERSE,
                    self::TYPE_PAPERS_REPOSITORY,
                ], true);
        });

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