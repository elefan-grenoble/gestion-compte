<?php

namespace Tests\AppBundle;

use AppBundle\DataFixtures\Purger\CustomPurger;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;

class DatabasePrimer extends WebTestCase
{
    /**
     * @throws Exception
     */
    public static function setUpBeforeClass(): void
    {
        $client = static::createClient();
        $container = $client->getKernel()->getContainer();

        $entityManager = $container->get('doctrine')->getManager();

        // Purge the database to start fresh
        $purger = new CustomPurger($entityManager);
        $purger->purge();
    }

    /**
     * @throws \Exception
     */
    public function loadFixturesWithGroups(array $group = null): void
    {

        $client = static::createClient();

        // re-run the fixture loading command
        $application = new Application($client->getKernel());
        $application->setAutoExit(false);

        $inputArray = [
            'command' => 'doctrine:fixtures:load',
            '--no-interaction' => true,
            '--quiet' => true,
        ];

        if ($group) {
            $inputArray['--group'] = $group;
        }

        $input = new ArrayInput($inputArray);

        $application->run($input);

    }
}




