<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/bin',
        __DIR__ . '/config',
        __DIR__ . '/public',
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    // Target PHP 8.3
    ->withPhpSets(php83: true)
    ->withSymfonySets(
        symfony70: true,
        annotationsToAttributes: true,
        codeQuality: true,
        constructorInjection: true,
    )
    ->withDoctrineSets(
        orm214: true,
        annotationsToAttributes: true,
        codeQuality: true,
    )
    // Register Symfony container for advanced rules (uncomment if var/cache/dev exists)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        privatization: true,
        instanceof: true,
        earlyReturn: true,
        strictBooleans: true
    );
