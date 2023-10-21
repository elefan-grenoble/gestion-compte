<?php

namespace AppBundle\DataFixtures\ORM;

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
        return;
        for ($i = 1; $i <= 50; $i++) {
            $shift = new Shift();

            $startDate = new DateTime('+' . rand(0, 30) . ' days');
            $endDate = (clone $startDate)->add(new DateInterval('PT' . rand(1, 8) . 'H'));
            $shift->setStart($startDate);
            $shift->setEnd($endDate);

            $bookedDate = new DateTime('-' . rand(0, 30) . ' days');
            $shift->setBookedTime($bookedDate);

            $shift->setWasCarriedOut((bool)rand(0, 1));
            $shift->setLocked((bool)rand(0, 1));
            $shift->setFixe((bool)rand(0, 1));

            $beneficiary = $this->getReference('beneficiary_' . $i);
            $beneficiary->addShift($shift);
            $shift->setShifter($beneficiary);

            $job = $this->getReference('job_' . rand(1, 10));
            $job->addShift($shift);
            $shift->setJob($job);

            $manager->persist($shift);
            $manager->persist($beneficiary);

        }

        $manager->flush();

        echo "50 Shifts created\n";
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
