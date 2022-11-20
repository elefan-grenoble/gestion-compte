<?php

namespace AppBundle\Repository;

/**
 * BeneficiaryRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class BeneficiaryRepository extends \Doctrine\ORM\EntityRepository
{
    public function findOneFromAutoComplete($str)
    {
        $reId = '/^#([0-9]+).*/';
        $reFirstname = '/(?<=\s)(.*?)(?=\s)/';
        preg_match_all($reId, $str, $matchesId, PREG_SET_ORDER, 0);
        preg_match_all($reFirstname, $str, $matchesFirstname, PREG_SET_ORDER, 0);
        if ((count($matchesId) == 1) && (count($matchesFirstname) == 1)) {
            $qb = $this->createQueryBuilder('b');

            $qb->leftJoin('b.membership', 'm')
                ->where('m.member_number = :membernumber')
                ->andWhere('b.firstname = :firstname')
                ->setParameter('membernumber', $matchesId[0][1])
                ->setParameter('firstname', $matchesFirstname[0][1]);

            return $qb->getQuery()->getSingleResult();
        }
        return null;
    }

    /**
     * findAllActive
     *
     * return all the active beneficiaries meaning with
     * an active membership
     */
    public function findAllActive()
    {

        $qb = $this->createQueryBuilder('beneficiary');

        $qb->select('beneficiary, membership')
            ->join('beneficiary.user', 'user')
            ->join('beneficiary.membership', 'membership')
            ->where('membership.withdrawn = 0');

        return $qb->getQuery()->getResult();
    }
}
