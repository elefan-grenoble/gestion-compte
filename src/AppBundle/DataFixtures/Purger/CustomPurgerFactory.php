<?php

namespace AppBundle\DataFixtures\Purger;

use Doctrine\Bundle\FixturesBundle\Purger\PurgerFactory;
use Doctrine\Common\DataFixtures\Purger\PurgerInterface;
use Doctrine\ORM\EntityManagerInterface;

class CustomPurgerFactory implements PurgerFactory
{
    /**
     * @param string|null $emName
     * @param EntityManagerInterface $em
     * @param array $excluded
     * @param bool $purgeWithTruncate
     * @return PurgerInterface
     */
    public function createForEntityManager(?string $emName, EntityManagerInterface $em, array $excluded = [], bool $purgeWithTruncate = false): PurgerInterface
    {
        return new CustomPurger($em);
    }
}