<?php

namespace AppBundle\DataFixtures\ORM;

use AppBundle\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Persistence\ObjectManager;

class UserFixtures extends Fixture implements FixtureInterface
{
    public function load(ObjectManager $manager)
    {

        // 50 users
        for ($i = 1; $i <= 50; $i++) {
            $user = new User();

            $user->setEmail('user' . $i . '.@email.com');
            $user->setPlainPassword('password');
            $user->setEnabled(true);
            $user->setRoles(array('ROLE_USER'));
            $user->setUsername('user' . $i);

            $this->addReference('user_' . $i, $user);

            $manager->persist($user);
        }

        // 1 admin
        $user = new User();
        $user->setUsername('admin');
        $user->setEmail('admin@email.com');
        $user->setPlainPassword('password');
        $user->setEnabled(true);
        $user->setRoles(array('ROLE_ADMIN'));

        $this->addReference('admin', $user);

        $manager->persist($user);


        $manager->flush();


        echo "50 users and 1 admin created\n";

    }



}