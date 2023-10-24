<?php

namespace AppBundle\DataFixtures\ORM;

use AppBundle\DataFixtures\FixturesConstants;
use AppBundle\Entity\Job;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class JobFixtures extends Fixture implements FixtureGroupInterface
{
    public function load(ObjectManager $manager)
    {
        $jobTitles = FixturesConstants::JOB_TITLES;
        $jobColors = FixturesConstants::JOB_COLORS;
        $jobDescriptions = FixturesConstants::JOB_DESCRIPTIONS;
        $adminsCount = FixturesConstants::ADMINS_COUNT;

        for ($i = 0; $i < 5; $i++) {
            $job = new Job();
            $job->setName($jobTitles[$i]);
            $job->setColor($jobColors[$i]);
            $job->setDescription($jobDescriptions[$i]);
            $job->setMinShifterAlert(rand(3, 5));

            if ($i == 4) {
                $job->setEnabled(false);
            } else {
                $job->setEnabled(true);
            }

            $job->setCreatedBy($this->getReference('admin_' . rand(1, $adminsCount)));

            $this->setReference('job_' . ($i+1), $job);
            $manager->persist($job);
        }

        $manager->flush();

        echo "4 Jobs created\n";
    }

    public static function getGroups(): array
    {
        return ['period'];
    }
}
