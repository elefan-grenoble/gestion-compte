<?php


namespace AppBundle\DataFixtures\ORM;

use AppBundle\DataFixtures\FixturesConstants;
use AppBundle\Entity\Client;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;


class ClientFixtures extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{

    public function load(ObjectManager $manager)
    {

        $clientCounts = FixturesConstants::CLIENTS_COUNT;

        for ($i = 1; $i <= $clientCounts; $i++) {
            $client = new Client();
            $client->setRandomId('client_id_' . $i);
            $client->setSecret('secret_' . $i);
            $client->setRedirectUris(['http://example.com/callback_' . $i]);
            $client->setAllowedGrantTypes(['password', 'refresh_token']);

            $client->setService($this->getReference('service_' . $i));

            $this->addReference('client_' . $i, $client);

            $manager->persist($client);
        }

        $manager->flush();

        echo $clientCounts . " clients created\n";

    }

    public static function getGroups(): array
    {
        return ['period'];
    }

    public function getDependencies(): array
    {
        return [
            ServiceFixtures::class,
        ];
    }

}