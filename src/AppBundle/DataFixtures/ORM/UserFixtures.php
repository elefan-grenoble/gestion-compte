<?php

namespace AppBundle\DataFixtures\ORM;

use AppBundle\DataFixtures\FixturesConstants;
use AppBundle\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Persistence\ObjectManager;

class UserFixtures extends Fixture implements FixtureInterface
{
    public function load(ObjectManager $manager)
    {

        $firstnames = FixturesConstants::FIRSTNAMES;
        $lastnames = FixturesConstants::LASTNAMES;

        // 50 users
        for ($i = 1; $i <= 50; $i++) {
            $user = new User();

            $user->setEmail( $firstnames[$i-1] . $lastnames[$i-1] . '@email.com');
            $user->setPlainPassword('password');
            $user->setEnabled(true);
            $user->setRoles(array('ROLE_USER'));
            $user->setUsername($firstnames[$i-1] . " " . $lastnames[$i-1]);

            $this->addReference('user_' . $i, $user);

            $manager->persist($user);
        }

        // 5 admin
        for ($i = 1; $i <= 5; $i++) {

            $user = new User();
            $user->setUsername('admin'.$i);
            $user->setEmail('admin'.$i.'@email.com');
            $user->setPlainPassword('password');
            $user->setEnabled(true);
            $user->setRoles(array('ROLE_ADMIN'));

            $this->addReference('admin_'.$i, $user);

            $manager->persist($user);
        }


        // 1 super admin
        $user = new User();
        $user->setUsername('admin');
        $user->setEmail('superadmin@email.com');
        $user->setPlainPassword('password');
        $user->setEnabled(true);
        $user->setRoles(array('ROLE_SUPER_ADMIN', 'ROLE_ADMIN'));

        $this->addReference('superadmin', $user);

        $manager->persist($user);

        $manager->flush();


        echo "50 users and 5 admin and 1 super admin created\n";

    }



}