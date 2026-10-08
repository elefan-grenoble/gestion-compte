<?php

use Symfony\Bridge\PhpUnit\DeprecationErrorHandler;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists(dirname(__DIR__) . '/config/bootstrap.php')) {
    require dirname(__DIR__) . '/config/bootstrap.php';
} elseif (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__) . '/.env');
}

// Report the deprecations triggered while the suites run (summary printed at
// the end of the PHPUnit output). The mode comes from
// SYMFONY_DEPRECATIONS_HELPER (phpunit.xml.dist): "weak" reports without
// ever failing a test. symfony/phpunit-bridge's own bootstrap is not used as
// it also forces the locale.
if ('disabled' !== getenv('SYMFONY_DEPRECATIONS_HELPER') && class_exists(DeprecationErrorHandler::class)) {
    DeprecationErrorHandler::register(getenv('SYMFONY_DEPRECATIONS_HELPER'));
}
