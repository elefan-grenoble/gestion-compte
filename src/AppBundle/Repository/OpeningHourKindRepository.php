<?php

namespace AppBundle\Repository;

/**
 * OpeningHourKindRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class OpeningHourKindRepository extends \Doctrine\ORM\EntityRepository
{
    public function findAll()
    {
        return $this->findBy(array(), array('name' => 'ASC'));
    }
}
