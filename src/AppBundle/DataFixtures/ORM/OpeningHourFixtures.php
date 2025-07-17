<?php

namespace AppBundle\DataFixtures\ORM;

use AppBundle\DataFixtures\FixturesConstants;
use AppBundle\DataFixtures\FixtureTools;
use AppBundle\Entity\OpeningHour;
use AppBundle\Entity\OpeningHourKind;
use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Exception;


class OpeningHourFixtures extends Fixture implements FixtureGroupInterface, OrderedFixtureInterface
{
    /**
     * @throws Exception
     */
    public function load(ObjectManager $manager)
    {

        $openingHourKindsNames = FixturesConstants::OPENING_HOUR_KINDS_NAMES;
        $openingHourKindsStartDates = FixturesConstants::OPENING_HOUR_KINDS_START_DATES;
        $openingHourKindsEndDates = FixturesConstants::OPENING_HOUR_KINDS_END_DATES;

        for ($i = 0; $i < count($openingHourKindsNames); $i++) {

            $openingType = new OpeningHourKind();
            $openingType->setName($openingHourKindsNames[$i]);
            $openingType->setEnabled(true);

            // first day of period
            $startDateTime = new DateTime($openingHourKindsStartDates[$i]);
            $openingType->setStartDate($startDateTime);

            // closing time
            $endDateTime = new DateTime($openingHourKindsEndDates[$i]);
            $openingType->setEndDate($endDateTime);

            // creator
            $creator = $this->getReference('admin_' . rand(1, FixturesConstants::ADMINS_COUNT));
            $openingType->setCreatedBy($creator);

            $this->addReference('opening_hour_kind_' . ($i+1), $openingType);

            $manager->persist($openingType);

            for ($j = 0; $j < 7; $j++) {

                $openingHour = new OpeningHour();

                // day of week
                $openingHour->setDayOfWeek($j % 7);

                // opening time
                $startDateTime = new DateTime();
                $startDateTime->setTime(rand(14,17), 0);
                $openingHour->setStart($startDateTime);

                // closing time
                $endDateTime = new DateTime();
                $endDateTime->setTime(rand(19,22), 0);
                $openingHour->setEnd($endDateTime);

                // closed
                $randBool = (bool)FixtureTools::biased_random(0, 1, 0.1);
                $openingHour->setClosed($randBool);

                // opening king
                $openingHour->setKind($openingType);

                $this->addReference('opening_hour_' . ($i+1) . ($j+1), $openingHour);


                $manager->persist($openingHour);

            }

        }

        echo count($openingHourKindsNames)." openingHourKinds created\n";
        echo "7 openingHours created\n";

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['period'];
    }

    public function getOrder(): int
    {
        return 12;
    }
}