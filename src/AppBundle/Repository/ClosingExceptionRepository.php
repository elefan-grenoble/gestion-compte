<?php

namespace AppBundle\Repository;

/**
 * ClosingExceptionRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class ClosingExceptionRepository extends \Doctrine\ORM\EntityRepository
{
    public function findAll()
    {
        return $this->findBy(array(), array('date' => 'DESC'));
    }

    public function findFutures()
    {
        $qb = $this->createQueryBuilder('ce')
            ->where('ce.date > :now')
            ->setParameter('now', new \Datetime('now'));

        $qb->orderBy('ce.date', 'ASC');

        return $qb
            ->getQuery()
            ->getResult();
    }
}
