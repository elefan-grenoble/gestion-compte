<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\MembershipLog;
use AppBundle\Entity\Membership;
use AppBundle\Entity\User;
use AppBundle\Event\MembershipEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MembershipEventListener implements EventSubscriberInterface
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public static function getSubscribedEvents()
    {
        return [
            MembershipEvent::CREATED => 'onMembershipCreated',
            MembershipEvent::BENEFICIARY_ADDED => 'onMembershipBeneficiaryAdded',
            MembershipEvent::BENEFICIARY_REMOVED => 'onMembershipBeneficiaryRemoved',
        ];
    }

    public function onMembershipCreated(MembershipEvent $event)
    {
        $log = new MembershipLog();
        $log->setMembership($event->getMembership());
        $log->setType("CREATED");
        $this->em->persist($log);
        $this->em->flush();
    }

    public function onMembershipBeneficiaryAdded(MembershipEvent $event)
    {
        $log = new MembershipLog();
        $log->setMembership($event->getMembership());
        $log->setType("BENEFICIARY_ADDED");
        $this->em->persist($log);
        $this->em->flush();
    }

    public function onMembershipBeneficiaryRemoved(MembershipEvent $event)
    {
        $log = new MembershipLog();
        $log->setMembership($event->getMembership());
        $log->setType("BENEFICIARY_REMOVED");
        $this->em->persist($log);
        $this->em->flush();
    }
}
