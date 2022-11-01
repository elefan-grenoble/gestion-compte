<?php

namespace AppBundle\Service;

use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Event;
use AppBundle\Entity\Membership;
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
        $qb = $this->em->getRepository('AppBundle:Proxy')->createQueryBuilder('p');

        $qb->where('p.event = :event')
            ->andWhere('p.giver = :membership')
            ->setParameter('event', $event)
            ->setParameter('membership', $membership);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function getReceivedProxiesOfBeneficiaryForAnEvent(Event $event, Beneficiary $beneficiary)
    {
        $qb = $this->em->getRepository('AppBundle:Proxy')->createQueryBuilder('p');

        $qb->where('p.event = :event')
            ->andWhere('p.owner IN (:beneficiaries)')
            ->setParameter('event', $event)
            ->setParameter('beneficiaries', $beneficiary->getMembership()->getBeneficiaries());

        // getResult instead of getOneOrNullResult? member can have multiple proxies (%max_event_proxy_per_member%)
        return $qb->getQuery()->getResult();
    }
}
