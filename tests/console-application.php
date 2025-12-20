<?php
// fichier utilisé par phpstan (https://github.com/phpstan/phpstan-symfony?tab=readme-ov-file#analysis-of-symfony-console-commands)
use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;

require __DIR__ . '/../config/bootstrap.php';
$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
return new Application($kernel);