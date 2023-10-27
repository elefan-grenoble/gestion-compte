<?php

namespace AppBundle\DataFixtures\ORM;

use AppBundle\DataFixtures\FixturesConstants;
use AppBundle\Entity\Membership;
use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Exception;

class MembershipFixtures extends Fixture implements OrderedFixtureInterface, FixtureGroupInterface
{

    /**
     * @throws Exception
     */
    public function load(ObjectManager $manager)
    {

        $roleGoesToId = FixturesConstants::ROLE_GOES_TO_ID;
        $adminsAmount = FixturesConstants::ADMINS_COUNT;
        $userAmount = FixturesConstants::USERS_COUNT;
        $superAdminsAmount = FixturesConstants::SUPER_ADMINS_COUNT;
        $membershipCount = $adminsAmount + $userAmount + $superAdminsAmount;

        for ($i = 1; $i <= $membershipCount; $i++) {
            $membership = new Membership();
            $membership->setMemberNumber($i);

            // one user is withdrawn
            if ($i == $roleGoesToId["WITHDRAWN"]) {
                $membership->setWithdrawn(1);

                // A withdrawn date between now and 2 years ago
                $date = new DateTime('-' . rand(0, 730) . ' days');
                $membership->setWithdrawnDate($date);
                $rand_admin_id = rand(1, $adminsAmount);
                $membership->setWithdrawnBy($this->getReference('admin_' . $rand_admin_id));

            } else {
                $membership->setWithdrawn(0);
            }


            // one user is frozen
            if ($i == $roleGoesToId["FROZEN"]) {
                $membership->setFrozen(1);
            } else {
                $membership->setFrozen(0);
            }

            // one user is frozen at end of cycle
            if ($i == $roleGoesToId["FROZEN_AT_END_OF_CYCLE"]) {
                $membership->setFrozenChange(1);
            } else {
                $membership->setFrozenChange(0);
            }

            // A first shift date between now and 2 years ago
            $date = new DateTime('-' . rand(0, 730) . ' days');
            $membership->setFirstShiftDate($date);

            // set beneficiary
            $beneficiary = $this->getReference('beneficiary_' . $i);
            $membership->setMainBeneficiary($beneficiary);
            $beneficiary->setMembership($membership);

            // set reference
            $this->addReference("membership_".$i, $membership);

            $manager->persist($membership);
            $manager->persist($beneficiary);
        }

        echo $membershipCount." memberships created\n";

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['period'];
    }

    public function getOrder(): int
    {
        return 11;
    }
}