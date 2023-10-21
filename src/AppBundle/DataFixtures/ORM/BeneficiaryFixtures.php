<?php

namespace AppBundle\DataFixtures\ORM;

use AppBundle\DataFixtures\FixturesConstants;
use AppBundle\Entity\Beneficiary;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class BeneficiaryFixtures extends Fixture implements DependentFixtureInterface
{

    public function load(ObjectManager $manager)
    {

        $firstnames = FixturesConstants::FIRSTNAMES;
        $lastnames = FixturesConstants::LASTNAMES;

        for ($i = 1; $i <= 4; $i++) {
            $beneficiary = new Beneficiary();
            $beneficiary->setFirstname($firstnames[$i-1]);
            $beneficiary->setLastname($lastnames[$i-1]);

            $lastDigits = $i;
            if ($lastDigits < 10) {
                $lastDigits = '0' . $lastDigits;
            }
            $beneficiary->setPhone('06123456' . $lastDigits);

            // Set Flying
            $beneficiary->setFlying(rand(0, 1) > 0.5);  // Randomly set true or false

            // Set CreatedAt
            $beneficiary->setCreatedAtValue();

            // Set User
            if ($i == 56) {
                $user = $this->getReference('superadmin');
            } else if ($i > 50) {
                $user = $this->getReference('admin_'.($i - 50));
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
