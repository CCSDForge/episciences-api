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
     * API Platform 5 removed the optional "hydra:" JSON-LD prefix and its legacy
     * normalizer. The "hydra_prefix" setting is no longer supported and must
     * therefore stay absent from the configuration.
     */
    public function testHydraPrefixConfigIsRemoved(): void
    {
        $config = Yaml::parseFile(dirname(__DIR__, 3) . '/config/packages/api_platform.yaml');

        $this->assertArrayNotHasKey(
            'hydra_prefix',
            $config['api_platform']['serializer'] ?? [],
            'api_platform.serializer.hydra_prefix was removed in API Platform 5 and must not be set.'
        );
    }
}
