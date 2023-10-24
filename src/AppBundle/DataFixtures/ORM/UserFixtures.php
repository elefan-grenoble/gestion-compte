<?php

namespace AppBundle\DataFixtures\ORM;

use AppBundle\DataFixtures\FixturesConstants;
use AppBundle\Entity\User;
use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Persistence\ObjectManager;

class UserFixtures extends Fixture implements FixtureInterface, FixtureGroupInterface
{
    public function load(ObjectManager $manager)
    {

        $firstnames = FixturesConstants::FIRSTNAMES;
        $lastnames = FixturesConstants::LASTNAMES;
        $adminsCount = FixturesConstants::ADMINS_COUNT;
        $userCount = FixturesConstants::USERS_COUNT;


        // 50 users
        for ($i = 1; $i <= $userCount; $i++) {
            $user = new User();

            $user->setEmail( $firstnames[$i-1] . $lastnames[$i-1] . '@email.com');
            $user->setPlainPassword('password');
            $user->setEnabled(true);
            $user->setRoles(array('ROLE_USER'));
            $user->setUsername($firstnames[$i-1] . " " . $lastnames[$i-1]);

            $this->addReference('user_' . $i, $user);

            $user->setLastLogin(new DateTime('now'));

            $manager->persist($user);
        }

        // 5 admin ( ids = 51 to 55 )
        for ($i = 1; $i <= $adminsCount; $i++) {

            $user = new User();
            $user->setUsername('admin'.$i);
            $user->setEmail('admin'.$i.'@email.com');
            $user->setPlainPassword('password');
            $user->setEnabled(true);
            $user->setRoles(array('ROLE_ADMIN'));
            $user->setLastLogin(new DateTime('now'));

            $this->addReference('admin_'.$i, $user);

            $manager->persist($user);
        }


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


        echo $userCount . " users and " . $adminsCount . " admin and 1 super admin created\n";

    }

    public static function getGroups(): array
    {
        return ['period'];
    }

}