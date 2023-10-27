<?php

namespace AppBundle\DataFixtures\ORM;

use AppBundle\DataFixtures\FixturesConstants;
use AppBundle\Entity\User;
use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class UserFixtures extends Fixture implements FixtureGroupInterface, OrderedFixtureInterface
{
    public function load(ObjectManager $manager)
    {

        $firstnames = FixturesConstants::FIRSTNAMES;
        $lastnames = FixturesConstants::LASTNAMES;
        $userCount = FixturesConstants::USERS_COUNT;

        // 50 users
        for ($i = 1; $i <= $userCount; $i++) {
            $user = new User();

            $user->setEmail($firstnames[$i - 1] . $lastnames[$i - 1] . '@email.com');
            $user->setPlainPassword('password');
            $user->setEnabled(true);
            $user->setRoles(array('ROLE_USER'));
            $user->setUsername($firstnames[$i - 1] . " " . $lastnames[$i - 1]);

            $this->addReference('user_' . $i, $user);

            $user->setLastLogin(new DateTime('now'));

            $manager->persist($user);
        }

        $manager->flush();

        echo $userCount . " users created\n";

    }

    public static function getGroups(): array
    {
        return ['period', 'user'];
    }

    public function getOrder(): int
    {
        return 1;
    }

}