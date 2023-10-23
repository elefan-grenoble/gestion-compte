<?php

namespace AppBundle\DataFixtures\ORM;

use AppBundle\DataFixtures\FixturesConstants;
use AppBundle\Entity\Registration;
use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Exception;

class RegistrationFixtures extends Fixture implements DependentFixtureInterface
{

    /**
     * @throws Exception
     */
    public function load(ObjectManager $manager)
    {

        $registration_amounts = FixturesConstants::REGISTRATION_AMOUNTS;
        $usersAmount = FixturesConstants::USERS_AMOUNT;
        $adminsAmount = FixturesConstants::ADMINS_AMOUNT;
        $superAdminsAmount = FixturesConstants::SUPER_ADMINS_AMOUNT;
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

        echo "50 registrations created\n";
    }

    public function getDependencies(): array
    {
        return [
            BeneficiaryFixtures::class,
        ];
    }
}
