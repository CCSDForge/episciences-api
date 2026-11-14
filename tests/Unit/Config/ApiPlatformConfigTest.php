<?php

declare(strict_types=1);

namespace App\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Configuration values the code depends on.
 */
class ApiPlatformConfigTest extends TestCase
{
    /**
     * API Platform 5 defaults the "hydra:" JSON-LD prefix to false, which strips
     * the "hydra:" prefix from the decorated collection output and breaks the
     * custom ApiCollectionNormalizer (it reads "hydra:totalItems"/"hydra:member"
     * and writes "hydra:totalPublishedArticles"/"hydra:range"). The migration
     * keeps the "hydra:" prefix by restoring api_platform.serializer.hydra_prefix
     * to true, so this setting must stay present and truthy.
     */
    public function testHydraPrefixConfigIsEnabled(): void
    {
        $config = Yaml::parseFile(dirname(__DIR__, 3) . '/config/packages/api_platform.yaml');

        $this->assertTrue(
            $config['api_platform']['serializer']['hydra_prefix'] ?? false,
            'api_platform.serializer.hydra_prefix must be true to keep the "hydra:" JSON-LD prefix.'
        );
    }
}
