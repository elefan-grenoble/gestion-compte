<?php

namespace AppBundle\DataFixtures\ORM;

use AppBundle\DataFixtures\FixturesConstants;
use AppBundle\Entity\Address;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class AddressFixtures extends Fixture implements DependentFixtureInterface
{

    public function load(ObjectManager $manager)
    {

        $addresses = FixturesConstants::ADDRESSES;


        for ($i = 0; $i < 50; $i++) {

            $address = new Address();

            $address->setStreet1($addresses[$i]);
            $address->setStreet2('Apartment ' . ($i+1));
            $address->setZipcode(rand(10000, 99999));
            $address->setCity('Grenoble');
            $beneficiary = $this->getReference('beneficiary_' . ($i+1));
            $beneficiary->setAddress($address);
            $address->setBeneficiary($beneficiary);

            $manager->persist($address);

        }

        $manager->flush();

        echo "50 Addresses created\n";
    }

    public function getDependencies(): array
    {
        return [
            BeneficiaryFixtures::class,
        ];
    }
}
