<?php

namespace AppBundle\DataFixtures\ORM;

use AppBundle\DataFixtures\FixturesConstants;
use AppBundle\Entity\Beneficiary;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class BeneficiaryFixtures extends Fixture implements OrderedFixtureInterface, FixtureGroupInterface
{

    public function load(ObjectManager $manager)
    {

        $firstnames = FixturesConstants::FIRSTNAMES;
        $lastnames = FixturesConstants::LASTNAMES;
        $roleGoesToId = FixturesConstants::ROLE_GOES_TO_ID;
        $usersAmount = FixturesConstants::USERS_COUNT;
        $adminsAmount = FixturesConstants::ADMINS_COUNT;
        $superAdminsAmount = FixturesConstants::SUPER_ADMINS_COUNT;

        $beneficiaryCount = $usersAmount + $adminsAmount + $superAdminsAmount;

        for ($i = 1; $i <= $beneficiaryCount; $i++) {
            $beneficiary = new Beneficiary();

            // set names according to roles and parameters
            $firstname = $firstnames[$i-1];

            if ($i == $roleGoesToId["WITHDRAWN"]) {
                $firstname.= ' (withdrawn)';
            }

            if ($i == $roleGoesToId["FROZEN"]) {
                $firstname.= ' (frozen)';
            }

            if ($i == $roleGoesToId["FROZEN_AT_END_OF_CYCLE"]) {
                $firstname.= ' (frozen at end of cycle)';
            }

            if (in_array($i, (array)$roleGoesToId["ROLE_ADMIN"]) ) {
                $firstname.= ' (admin)';
            }

            if ($i == $roleGoesToId["ROLE_SUPER_ADMIN"]) {
                $firstname.= ' (super admin)';
            }

            if ($i == $roleGoesToId["OWNER_OF_FIRST_COMMISSION"]) {
                $firstname.= ' (owner of commission 1)';
            } else if (in_array($i, (array)$roleGoesToId["IN_FIRST_COMMISSION"]) ) {
                $firstname.= ' (commission 1)';
            }

            if ($i == $roleGoesToId["OWNER_OF_SECOND_COMMISSION"]) {
                $firstname.= ' (owner of commission 2)';
            } else if (in_array($i, (array)$roleGoesToId["IN_SECOND_COMMISSION"]) ) {
                $firstname.= ' (commission 2)';
            }

            if ($i == $roleGoesToId["OWNER_OF_THIRD_COMMISSION"]) {
                $firstname.= ' (owner of commission 3)';
            } else if (in_array($i, (array)$roleGoesToId["IN_THIRD_COMMISSION"]) ) {
                $firstname.= ' (commission 3)';
            }

            if ($i == $roleGoesToId["OWNER_OF_FOURTH_COMMISSION"]) {
                $firstname.= ' (owner of commission 4)';
            } else if (in_array($i, (array)$roleGoesToId["IN_FOURTH_COMMISSION"]) ) {
                $firstname.= ' (commission 4)';
            }

            $beneficiary->setFirstname($firstname);
            $beneficiary->setLastname($lastnames[$i-1]);

            $lastDigits = $i;
            if ($lastDigits < 10) {
                $lastDigits = '0' . $lastDigits;
            }
            $beneficiary->setPhone('06123456' . $lastDigits);

            // Set Flying
            $beneficiary->setFlying((bool)rand(0, 1));  // Randomly set true or false

            // Set CreatedAt
            $beneficiary->setCreatedAtValue();

            // Set User
            if ($i == $roleGoesToId["ROLE_SUPER_ADMIN"]) {
                $user = $this->getReference('superadmin');
            } else if (in_array($i, (array)$roleGoesToId["ROLE_ADMIN"]) ) {
                $user = $this->getReference('admin_'.($i - 50));
            } else {
                $user = $this->getReference('user_' . $i);
            }
            $beneficiary->setUser($user);
            $user->setBeneficiary($beneficiary);

            // set reference for other fixtures
            $this->addReference('beneficiary_' . $i, $beneficiary);

            $manager->persist($beneficiary);
            $manager->persist($user);

        }

        $manager->flush();

        echo $beneficiaryCount." beneficiaries created\n";
    }

    public static function getGroups(): array
    {
        return ['period'];
    }

    public function getOrder()
    {
        return 6;
    }

}
