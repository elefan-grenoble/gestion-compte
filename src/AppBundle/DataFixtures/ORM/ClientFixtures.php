<?php


namespace AppBundle\DataFixtures\ORM;

use AppBundle\Entity\Client;
use AppBundle\Entity\Service;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Persistence\ObjectManager;


class ClientFixtures extends Fixture implements FixtureInterface, FixtureGroupInterface
{

    public function load(ObjectManager $manager)
    {
        return;
        for ($i = 1; $i <= 10; $i++) {
            $client = new Client();
            $client->setRandomId('client_id_' . $i);
            $client->setSecret('secret_' . $i);
            $client->setRedirectUris(['http://example.com/callback_' . $i]);
            $client->setAllowedGrantTypes(['password', 'refresh_token']);

            // set reference for other fixtures
            $this->addReference('client_' . $i, $client);

            $manager->persist($client);
        }

        $manager->flush();

        echo "10 Clients created\n";

    }

    public static function getGroups(): array
    {
        return ['period'];
    }

}