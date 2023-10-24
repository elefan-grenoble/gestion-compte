<?php

namespace AppBundle\DataFixtures\ORM;

use AppBundle\DataFixtures\FixturesConstants;
use AppBundle\Entity\Registration;
use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Exception;

class RegistrationFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{

    /**
     * @throws Exception
     */
    public function load(ObjectManager $manager)
    {

        $registration_amounts = FixturesConstants::REGISTRATION_AMOUNTS;
        $usersAmount = FixturesConstants::USERS_COUNT;
        $adminsAmount = FixturesConstants::ADMINS_COUNT;
        $superAdminsAmount = FixturesConstants::SUPER_ADMINS_COUNT;
        $roleGoesToId = FixturesConstants::ROLE_GOES_TO_ID;


        for ($i = 1; $i <= ($usersAmount + $adminsAmount + $superAdminsAmount); $i++) {

            $registration = new Registration();

            // A registration date between yersterday and 1 year ago
            $registration_date = new DateTime('-' . rand(1, 363) . ' days');
            $registration->setDate($registration_date);

            $registration->setAmount([$registration_amounts["MINIMUM"], $registration_amounts["MAXIMUM"]][rand(0, 1)]);
            $registration->setMode(rand(1, 6));

            // associate a member
            $membership = $this->getReference("membership_".$i);
            $registration->setMembership($membership);
            $membership->addRegistration($registration);

            // set registrar
            if (in_array($i, (array)$roleGoesToId["ROLE_ADMIN"])) {
                $registrar = $this->getReference('superadmin');
            } else {
                $rand_admin_id = rand(1, $adminsAmount);
                $registrar = $this->getReference('admin_'. $rand_admin_id);
            }

            $registration->setRegistrar($registrar);
            $registrar->addRecordedRegistration($registration);

            $manager->persist($registration);
            $manager->persist($membership);
            $manager->persist($registrar);

        }

        $manager->flush();

        echo "56 registrations created\n";
    }

    public function getDependencies(): array
    {
        return [
            BeneficiaryFixtures::class,
        ];
    }

    public static function getGroups(): array
    {
        return ['period'];
    }
}
