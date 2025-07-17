<?php

namespace AppBundle\DataFixtures\ORM;

use AppBundle\Entity\User;
use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class SuperAdminFixtures extends Fixture implements FixtureGroupInterface, OrderedFixtureInterface
{
    public function load(ObjectManager $manager)
    {

        // 1 super admin ( id = 56 )
        $user = new User();
        $user->setUsername('admin');
        $user->setEmail('superadmin@email.com');
        $user->setPlainPassword('password');
        $user->setEnabled(true);
        $user->setRoles(array('ROLE_SUPER_ADMIN', 'ROLE_ADMIN'));
        $user->setLastLogin(new DateTime('now'));

        $this->addReference('superadmin', $user);

        $manager->persist($user);

        $manager->flush();


        echo "1 super admin created\n";

    }

    public static function getGroups(): array
    {
        return ['period', 'superadmin'];
    }

    public function getOrder(): int
    {
        return 3;
    }

}