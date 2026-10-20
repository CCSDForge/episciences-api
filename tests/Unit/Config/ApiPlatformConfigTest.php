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
     * The "hydra:" keys are written as is by App\Serializer\Normalizer\ApiCollectionNormalizer
     * and App\OpenApi\OpenApiNormalizer: they require the prefix to be enabled.
     */
    public function testHydraPrefixIsEnabled(): void
    {
        $config = Yaml::parseFile(dirname(__DIR__, 3) . '/config/packages/api_platform.yaml');

        $this->assertTrue(
            $config['api_platform']['serializer']['hydra_prefix'] ?? false,
            'api_platform.serializer.hydra_prefix must stay true: the "hydra:" keys are hardcoded in the normalizers.'
        );
    }
}
