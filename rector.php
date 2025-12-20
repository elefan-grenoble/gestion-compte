<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withComposerBased(true, true, true, true)
    ->withSymfonyContainerXml(__DIR__ . '/var/cache/dev/srcApp_KernelDevDebugContainer.xml')
    ->withMemoryLimit('512M')
    ->withParallel(2000,2)
    ->withTypeCoverageLevel(0);
