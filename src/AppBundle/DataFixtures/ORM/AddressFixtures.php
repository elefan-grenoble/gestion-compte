<?php

namespace AppBundle\DataFixtures\ORM;

use AppBundle\DataFixtures\FixturesConstants;
use AppBundle\Entity\Address;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class AddressFixtures extends Fixture implements FixtureGroupInterface, OrderedFixtureInterface
{

    public function load(ObjectManager $manager)
    {

        $addresses = FixturesConstants::ADDRESSES;
        $addresses_count = FixturesConstants::ADMINS_COUNT + FixturesConstants::USERS_COUNT + FixturesConstants::SUPER_ADMINS_COUNT;

        for ($i = 0; $i < $addresses_count; $i++) {

            $address = new Address();

            $address->setStreet1($addresses[$i]);
            $address->setStreet2('Apartment ' . ($i+1));
            $address->setZipcode(rand(10000, 99999));
            $address->setCity('Grenoble');

            // set beneficiary
            $beneficiary = $this->getReference('beneficiary_' . ($i+1));
            $beneficiary->setAddress($address);
            $address->setBeneficiary($beneficiary);

            $this->addReference('address_' . ($i+1), $address);

            $manager->persist($address);

        }

        $manager->flush();

        echo $addresses_count . " addresses created\n";
    }

    public static function getGroups(): array
    {
        return ['period'];
    }

    public function getOrder()
    {
        return 16;
    }

}
