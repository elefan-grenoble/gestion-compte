<?php

namespace AppBundle\DataFixtures\ORM;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use AppBundle\Entity\Address;

class AddressFixtures extends Fixture implements DependentFixtureInterface
{

    private $addresses = [];

    public function load(ObjectManager $manager)
    {
        for ($i = 1; $i <= 50; $i++) {

            $address = new Address();

            $address->setStreet1('Street Name ' . $i);
            $address->setStreet2('Apartment ' . $i);
            $address->setZipcode(str_pad($i, 5, '0', STR_PAD_LEFT));  // Ensure 5 digits
            $address->setCity('City Name ' . $i);

            $beneficiary = $this->getReference('beneficiary_' . $i);
            $beneficiary->setAddress($address);
            $address->setBeneficiary($beneficiary);

            $addresses[] = $address;
            $manager->persist($address);

        }

        $manager->flush();

        echo "50 Addresses created\n";
    }

    public function getAddresses()
    {
        return $this->addresses;
    }

    public function getDependencies(): array
    {
        return [
            BeneficiaryFixtures::class,
        ];
    }
}
