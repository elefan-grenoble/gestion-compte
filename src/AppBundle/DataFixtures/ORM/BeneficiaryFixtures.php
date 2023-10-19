<?php

namespace AppBundle\DataFixtures\ORM;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Persistence\ObjectManager;
use AppBundle\Entity\Beneficiary;

class BeneficiaryFixtures extends Fixture implements DependentFixtureInterface
{

    public function load(ObjectManager $manager)
    {

        for ($i = 1; $i <= 51; $i++) {
            $beneficiary = new Beneficiary();
            $beneficiary->setFirstname('Firstname' . $i);
            $beneficiary->setLastname('Lastname' . $i);
            $beneficiary->setPhone('123456789' . $i);

            // Set Flying
            $beneficiary->setFlying(rand(0, 1) > 0.5);  // Randomly set true or false

            // Set CreatedAt
            $beneficiary->setCreatedAtValue();

            // Set User
            if ($i == 51) {
                $user = $this->getReference('admin');
            } else {
                $user = $this->getReference('user_' . $i);
            }
            $beneficiary->setUser($user);
            $user->setBeneficiary($beneficiary);

            // set reference for other fixtures
            $this->addReference('beneficiary_' . $i, $beneficiary);

            $this->beneficiaries[] = $beneficiary;

            $manager->persist($beneficiary);
            $manager->persist($user);

        }

        $manager->flush();

        echo "50 Beneficiaries created\n";
    }

    public function getBeneficiaries()
    {
        return $this->beneficiaries;
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
        ];
    }

}
