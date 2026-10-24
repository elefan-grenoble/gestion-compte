<?php

namespace App\Tests\Support;

use Doctrine\ORM\EntityManagerInterface;

/**
 * For KernelTestCase classes: saves built entities through the entity
 * manager of the kernel currently booted, so they land in the transaction
 * the test runs in (see DatabaseIsolationExtension) and are rolled back with
 * it.
 */
trait PersistsEntities
{
    protected static function entityManager(): EntityManagerInterface
    {
        if (null === static::$container) {
            static::bootKernel();
        }

        return static::$container->get('doctrine')->getManager();
    }

    /**
     * Persists and flushes the given entities; returns the first one.
     *
     * @template T of object
     *
     * @param T $entity
     *
     * @return T
     */
    protected static function persist(object $entity, object ...$others): object
    {
        $em = static::entityManager();
        foreach (array_merge([$entity], $others) as $each) {
            $em->persist($each);
        }
        $em->flush();

        return $entity;
    }
}
