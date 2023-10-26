<?php

namespace Tests\AppBundle;

use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;

class DatabasePrimer extends WebTestCase
{
    public static function setUpBeforeClass(): void
    {
        $client = static::createClient();
        $container = $client->getKernel()->getContainer();

        $entityManager = $container->get('doctrine')->getManager();

        // Purge the database to start fresh
        $purger = new ORMPurger($entityManager);
        $purger->purge();

        // now re-run the fixture loading command,
        // or call the service that loads your fixtures
        $application = new Application($client->getKernel());
        $application->setAutoExit(false);

        $input = new ArrayInput([
            'command' => 'doctrine:fixtures:load',
            '--no-interaction' => true,
            '--quiet' => true
        ]);
        $application->run($input);
    }
}




