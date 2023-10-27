<?php

namespace AppBundle\DataFixtures\ORM;

use AppBundle\DataFixtures\FixturesConstants;
use AppBundle\Entity\User;
use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class AdminFixtures extends Fixture implements FixtureGroupInterface, OrderedFixtureInterface
{
    public function load(ObjectManager $manager)
    {

        $adminsCount = FixturesConstants::ADMINS_COUNT;

        // 5 admin ( ids = 51 to 55 )
        for ($i = 1; $i <= $adminsCount; $i++) {

            $user = new User();
            $user->setUsername('admin' . $i);
            $user->setEmail('admin' . $i . '@email.com');
            $user->setPlainPassword('password');
            $user->setEnabled(true);
            $user->setRoles(array('ROLE_ADMIN'));
            $user->setLastLogin(new DateTime('now'));

            $this->addReference('admin_' . $i, $user);

            $manager->persist($user);
        }

        $manager->flush();

        echo $adminsCount . " admins created\n";

    }

    public static function getGroups(): array
    {
        return ['period', 'admin'];
    }

    public function getOrder()
    {
        return 2;
    }

}