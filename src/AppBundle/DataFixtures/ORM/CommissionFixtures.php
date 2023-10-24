<?php

namespace AppBundle\DataFixtures\ORM;

use AppBundle\DataFixtures\FixturesConstants;
use AppBundle\Entity\Commission;
use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Exception;

class CommissionFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    /**
     * @throws Exception
     */
    public function load(ObjectManager $manager)
    {
        $commissions = FixturesConstants::COMMISSIONS;
        $descriptions = FixturesConstants::DESCRIPTIONS;
        $nextMeetingDescriptions = FixturesConstants::NEXTMEETINGDESCRIPTIONS;
        $roleGoesToId = FixturesConstants::ROLE_GOES_TO_ID;
        $commissionsCount = FixturesConstants::COMMISSIONS_COUNT;

        for ($i = 0; $i < $commissionsCount; $i++) {
            $commission = new Commission();
            $commission->setName($commissions[$i]);
            $commission->setDescription($descriptions[$i]);
            $commission->setEmail($commissions[$i] . '@yourcoop.fr');
            $commission->setNextMeetingDesc($nextMeetingDescriptions[$i]);

            // a meeting between now and 2 months later
            $date = new DateTime('+' . rand(0, 60) . ' days');
            $commission->setNextMeetingDate($date);

            // define owner
            if ($i == 0) {
                $ownerId = $roleGoesToId["OWNER_OF_FIRST_COMMISSION"];
            } else if ($i == 1) {
                $ownerId = $roleGoesToId["OWNER_OF_SECOND_COMMISSION"];
            } else if ($i == 2 ) {
                $ownerId = $roleGoesToId["OWNER_OF_THIRD_COMMISSION"];
            } else if ($i == 3 ) {
                $ownerId = $roleGoesToId["OWNER_OF_FOURTH_COMMISSION"];
            } else {
                $ownerId = $i + 1;
            }

            $beneficiary = $this->getReference('beneficiary_' . $ownerId);
            $commission->addBeneficiary($beneficiary);
            $commission->addOwner($beneficiary);
            $beneficiary->setOwn($commission);
            $beneficiary->addCommission($commission);

            // add beneficiaries for each commission
            if ($i == 0) {
                foreach ((array)$roleGoesToId["IN_FIRST_COMMISSION"] as $j) {
                    $beneficiary = $this->getReference('beneficiary_' . $j);
                    $commission->addBeneficiary($beneficiary);
                    $beneficiary->addCommission($commission);
                }
            }

            if ($i == 1) {
                foreach ((array)$roleGoesToId["IN_SECOND_COMMISSION"] as $j) {
                    $beneficiary = $this->getReference('beneficiary_' . $j);
                    $commission->addBeneficiary($beneficiary);
                    $beneficiary->addCommission($commission);
                }
            }
            if ($i == 2) {
                foreach ((array)$roleGoesToId["IN_THIRD_COMMISSION"] as $j) {
                    $beneficiary = $this->getReference('beneficiary_' . $j);
                    $commission->addBeneficiary($beneficiary);
                    $beneficiary->addCommission($commission);
                }
            }

            if ($i == 3) {
                foreach ((array)$roleGoesToId["IN_FOURTH_COMMISSION"] as $j) {
                    $beneficiary = $this->getReference('beneficiary_' . $j);
                    $commission->addBeneficiary($beneficiary);
                    $beneficiary->addCommission($commission);
                }
            }

            // set reference
            $this->addReference('commission_' . ($i+1), $commission);

            $manager->persist($commission);
            $manager->persist($beneficiary);
        }

        $manager->flush();

        echo $commissionsCount." commissions with owners and 6 members created \n";
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
