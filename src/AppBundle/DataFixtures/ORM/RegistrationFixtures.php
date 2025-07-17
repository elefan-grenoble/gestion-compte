<?php

namespace AppBundle\DataFixtures\ORM;

use AppBundle\DataFixtures\FixturesConstants;
use AppBundle\Entity\Registration;
use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Exception;

class RegistrationFixtures extends Fixture implements OrderedFixtureInterface, FixtureGroupInterface
{

    /**
     * @throws Exception
     */
    public function load(ObjectManager $manager)
    {

        $registration_amounts = FixturesConstants::REGISTRATION_AMOUNTS;
        $usersCount = FixturesConstants::USERS_COUNT;
        $adminsCount = FixturesConstants::ADMINS_COUNT;
        $superAdminsCount = FixturesConstants::SUPER_ADMINS_COUNT;
        $roleGoesToId = FixturesConstants::ROLE_GOES_TO_ID;
        $registrationCount = $usersCount + $adminsCount + $superAdminsCount;

        for ($i = 1; $i <= $registrationCount; $i++) {

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
                $rand_admin_id = rand(1, $adminsCount);
                $registrar = $this->getReference('admin_'. $rand_admin_id);
            }

            $registration->setRegistrar($registrar);
            $registrar->addRecordedRegistration($registration);

            $manager->persist($registration);
            $manager->persist($membership);
            $manager->persist($registrar);

        }

        $manager->flush();

        echo $registrationCount . " registrations created\n";
    }

    public static function getGroups(): array
    {
        return ['period'];
    }

    public function getOrder(): int
    {
        return 14;
    }
}
