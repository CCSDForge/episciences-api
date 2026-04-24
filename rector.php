<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\Symfony\Set\SymfonySetList;

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
    ->withAttributesSets(symfony: true, doctrine: true)
    ->withComposerBased(doctrine: true, symfony: true)
    ->withSets([
        DoctrineSetList::DOCTRINE_CODE_QUALITY,
        SymfonySetList::SYMFONY_CODE_QUALITY,
        SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION,
    ])
    // Register Symfony container for advanced rules (uncomment if var/cache/dev exists)
    ->withPreparedSets(
        deadCode: false,
        codeQuality: true,
        typeDeclarations: true,
        privatization: false,
        instanceof: true,
        earlyReturn: false,
        strictBooleans: true
    );
