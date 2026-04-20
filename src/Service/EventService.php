<?php

namespace App\Service;

use App\Entity\Beneficiary;
use App\Entity\Event;
use App\Entity\Membership;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Proxy;

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
        $qb = $this->em->getRepository(Proxy::class)->createQueryBuilder('p');

        $qb->where('p.event = :event')
            ->andWhere('p.giver = :membership')
            ->setParameter('event', $event)
            ->setParameter('membership', $membership);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function getReceivedProxiesOfBeneficiaryForAnEvent(Event $event, Beneficiary $beneficiary)
    {
        $qb = $this->em->getRepository(Proxy::class)->createQueryBuilder('p');

        $qb->where('p.event = :event')
            ->andWhere('p.owner IN (:beneficiaries)')
            ->setParameter('event', $event)
            ->setParameter('beneficiaries', $beneficiary->getMembership()->getBeneficiaries());

        // getResult instead of getOneOrNullResult? member can have multiple proxies (%max_event_proxy_per_member%)
        return $qb->getQuery()->getResult();
    }
}
