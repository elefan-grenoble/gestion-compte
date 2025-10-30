<?php
// fichier utilisé par phpstan (https://github.com/phpstan/phpstan-doctrine?tab=readme-ov-file#configuration)
use App\Kernel;

require __DIR__ . '/../config/bootstrap.php';
$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();
return $kernel->getContainer()->get('doctrine')->getManager();