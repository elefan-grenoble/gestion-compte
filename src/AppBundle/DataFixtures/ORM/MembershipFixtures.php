<?php

namespace AppBundle\DataFixtures\ORM;

use AppBundle\Entity\Membership;
use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class MembershipFixtures extends Fixture implements DependentFixtureInterface
{

    public function load(ObjectManager $manager)
    {

        for ($i = 1; $i <= 50; $i++) {
            $membership = new Membership();
            $membership->setMemberNumber($i);
            $membership->setWithdrawn((bool)random_int(0, 1));

            // A withdrawn date between now and 2 years ago
            $date = new DateTime('-' . rand(0, 730) . ' days');
            $membership->setWithdrawnDate($date);

            $membership->setWithdrawnBy($this->getReference('user_' . $i));
            $membership->setFrozen((bool)random_int(0, 1));
            $membership->setFrozenChange((bool)random_int(0, 1));

            $manager->persist($membership);
        }

        echo "50 Memberships created\n";

        $manager->flush();
    }

    public function getDependencies()
    {
        return [
            UserFixtures::class,
        ];
    }
}