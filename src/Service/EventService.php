<?php

namespace App\Service;

use App\Entity\Beneficiary;
use App\Entity\Event;
use App\Entity\Membership;
use Doctrine\ORM\EntityManagerInterface;

class EventService
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function getGivenProxyOfMembershipForAnEvent(Event $event, Membership $membership)
    {
        $qb = $this->em->getRepository('App:Proxy')->createQueryBuilder('p');

        $qb->where('p.event = :event')
            ->andWhere('p.giver = :membership')
            ->setParameter('event', $event)
            ->setParameter('membership', $membership);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function getReceivedProxyOfBeneficiaryForAnEvent(Event $event, Beneficiary $beneficiary)
    {
        $qb = $this->em->getRepository('App:Proxy')->createQueryBuilder('p');

        $qb->where('p.event = :event')
            ->andWhere( 'p.owner = :beneficiary')
            ->setParameter('event', $event)
            ->setParameter('beneficiary', $beneficiary);

        return $qb->getQuery()->getOneOrNullResult();
    }
}