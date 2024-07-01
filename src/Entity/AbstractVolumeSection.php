<?php

namespace App\Entity;

use App\Traits\ToolsTrait;

abstract class AbstractVolumeSection
{

    use ToolsTrait;
    protected array $committee;
    protected int $totalPublishedArticles;

    public function __construct(array $options = [])
    {
        $this->setOptions($options);
    }

    /**
     * @return array
     */
    public function getCommittee(): array
    {
        return $this->committee;
    }

    /**
     * @param array $committee
     * @return AbstractVolumeSection
     */
    public function setCommittee(array $committee): self
    {
        $this->committee = $committee;
        return $this;
    }

    /**
     * @return int
     */
    public function getTotalPublishedArticles(): int
    {
        return $this->totalPublishedArticles;
    }

    /**
     * @param int $totalPublishedArticles
     * @return AbstractVolumeSection
     */
    public function setTotalPublishedArticles(int $totalPublishedArticles = 0): self
    {
        $this->totalPublishedArticles = $totalPublishedArticles;
        return $this;
    }

    public function setOptions(array $options): void
    {
        $classMethods = get_class_methods($this);
        foreach ($options as $key => $value) {
            $key = self::convertToCamelCase($key, '_', true);
            $method = 'set' . $key;
            if (in_array($method, $classMethods, true)) {
                $this->$method($value);
            }
        }
    }

}