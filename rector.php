<?php
use Rector\Symfony\Set\SymfonySetList;
use Rector\Config\RectorConfig;
use Rector\Doctrine\Set\DoctrineSetList;

//vendor/bin/rector process src --dry-run:

// vendor/bin/rector process src --dry-run

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/src'
    ]);
    //$rectorConfig->symfonyContainerXml(__DIR__ . '/var/cache/dev/App_KernelDevDebugContainer.xml');
    $rectorConfig->sets([
        //SymfonySetList::SYMFONY_64,
        //SymfonySetList::SYMFONY_CODE_QUALITY,
        //SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION
    ]);

    $rectorConfig->sets([
        DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES,
        SymfonySetList::ANNOTATIONS_TO_ATTRIBUTES,

    ]);


};

################ RUN
// vendor/bin/rector process src --dry-run
// Rector will show you diff of files that it would change. To make the changes, drop --dry-run:
// vendor/bin/rector process src
// e.g. vendor/bin/rector process src/Entity/Section.php src/Entity/SectionSetting.php

