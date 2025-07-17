<?php

namespace AppBundle\DataFixtures\ORM;

use AppBundle\DataFixtures\FixturesConstants;
use AppBundle\Entity\Commission;
use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Exception;

class CommissionFixtures extends Fixture implements FixtureGroupInterface, OrderedFixtureInterface
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
        $ownersCount = 0;
        $beneficiariesCount = 0;

        for ($i = 0; $i < $commissionsCount; $i++) {
            $commission = new Commission();
            $commission->setName($commissions[$i]);
            $commission->setDescription($descriptions[$i]);
            $commission->setEmail($commissions[$i] . '@yourcoop.fr');
            $commission->setNextMeetingDesc($nextMeetingDescriptions[$i]);

            // a meeting between now and 2 months later
            $date = new DateTime('+' . rand(0, 60) . ' days');
            $commission->setNextMeetingDate($date);

            // if beneficiaries have been set ( in order for group commission to function )
            if ($this->hasReference('beneficiary_1')) {

                $ownersCount = 4;
                $beneficiariesCount = 6;

                // define owner
                if ($i == 0) {
                    $ownerId = $roleGoesToId["OWNER_OF_FIRST_COMMISSION"];
                } else if ($i == 1) {
                    $ownerId = $roleGoesToId["OWNER_OF_SECOND_COMMISSION"];
                } else if ($i == 2) {
                    $ownerId = $roleGoesToId["OWNER_OF_THIRD_COMMISSION"];
                } else if ($i == 3) {
                    $ownerId = $roleGoesToId["OWNER_OF_FOURTH_COMMISSION"];
                } else {
                    $ownerId = $i + 1;
                }

                $beneficiary_owner = $this->getReference('beneficiary_' . $ownerId);
                $commission->addBeneficiary($beneficiary_owner);
                $commission->addOwner($beneficiary_owner);
                $beneficiary_owner->setOwn($commission);
                $beneficiary_owner->addCommission($commission);

                $manager->persist($beneficiary_owner);

                // add beneficiaries for each commission
                if ($i == 0) {
                    foreach ((array)$roleGoesToId["IN_FIRST_COMMISSION"] as $j) {
                        $beneficiary = $this->getReference('beneficiary_' . $j);
                        $commission->addBeneficiary($beneficiary);
                        $beneficiary->addCommission($commission);
                        $manager->persist($beneficiary);
                    }
                }

                if ($i == 1) {
                    foreach ((array)$roleGoesToId["IN_SECOND_COMMISSION"] as $j) {
                        $beneficiary = $this->getReference('beneficiary_' . $j);
                        $commission->addBeneficiary($beneficiary);
                        $beneficiary->addCommission($commission);
                        $manager->persist($beneficiary);
                    }
                }
                if ($i == 2) {
                    foreach ((array)$roleGoesToId["IN_THIRD_COMMISSION"] as $j) {
                        $beneficiary = $this->getReference('beneficiary_' . $j);
                        $commission->addBeneficiary($beneficiary);
                        $beneficiary->addCommission($commission);
                        $manager->persist($beneficiary);
                    }
                }

                if ($i == 3) {
                    foreach ((array)$roleGoesToId["IN_FOURTH_COMMISSION"] as $j) {
                        $beneficiary = $this->getReference('beneficiary_' . $j);
                        $commission->addBeneficiary($beneficiary);
                        $beneficiary->addCommission($commission);
                        $manager->persist($beneficiary);
                    }
                }
            }

            // set reference
            $this->addReference('commission_' . ($i + 1), $commission);

            $manager->persist($commission);
        }

        $manager->flush();

        echo $commissionsCount . " commissions with ". $ownersCount ." owners and " . $beneficiariesCount . " beneficiaries each created \n";
    }


    public static function getGroups(): array
    {
        return ['period', 'commission'];
    }

    public function getOrder(): int
    {
        return 8;
    }


}
