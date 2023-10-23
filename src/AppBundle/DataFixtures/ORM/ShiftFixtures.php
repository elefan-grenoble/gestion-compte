<?php

namespace AppBundle\DataFixtures\ORM;

use AppBundle\DataFixtures\FixturesConstants;
use AppBundle\Entity\Shift;
use DateInterval;
use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Exception;

class ShiftFixtures extends Fixture implements DependentFixtureInterface
{
    /**
     * @throws Exception
     */
    public function load(ObjectManager $manager)
    {

        $usersCount = FixturesConstants::USERS_COUNT;
        $adminsCount = FixturesConstants::ADMINS_COUNT;
        $enabled_jobs_count = FixturesConstants::JOBS_COUNT;

        for ($i = 1; $i <= 20; $i++) {

            $randJobId = rand(1, $enabled_jobs_count);
            $job = $this->getReference('job_' . $randJobId);

            $randomTime = rand(9, 18);
            $startDate = new DateTime('+' . $i . ' days');
            $startDate->setTime($randomTime, 0, 0);

            $endDate = (clone $startDate)->add(new DateInterval('PT' . 2 . 'H'));

            $creator = $this->getReference('admin_' . rand(1, $adminsCount));

            $formation = $this->getReference('formation_'.$randJobId);

            // iterate on shifters
            for ($j = 1; $j<= rand(1,5); $j++) {

                $shift = new Shift();

                $shift->setStart($startDate);
                $shift->setEnd($endDate);

                $job->addShift($shift);
                $shift->setJob($job);

                $shift->setCreatedBy($creator);

                $isBooked = (bool)rand(0, 1);

                $shift->setFormation($formation);

                if ($isBooked) {
                    $bookedDate = new DateTime('-' . rand(0, 30) . ' days');
                    $shift->setBookedTime($bookedDate);

                    $shift->setWasCarriedOut((bool)rand(0, 1));
                    $shift->setLocked((bool)rand(0, 1));
                    $shift->setFixe((bool)rand(0, 1));

                    $shift->setBooker($this->getReference('admin_' . rand(1, $adminsCount)));

                    $beneficiary = $this->getReference('beneficiary_' . rand(1, $usersCount));
                    $beneficiary->addShift($shift);
                    $shift->setShifter($beneficiary);

                    $shift->setFixe(rand(0,1));

                    $manager->persist($beneficiary);
                }

                $manager->persist($job);
                $manager->persist($shift);
            }
        }

        $manager->flush();

        echo "20 Shifts for a random number of beneficiaries created\n";
    }

    public function getDependencies()
    {
        return [
            UserFixtures::class,
            BeneficiaryFixtures::class,
            JobFixtures::class,
        ];
    }
}
