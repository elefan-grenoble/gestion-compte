<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\User;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class BeneficiaryInitializationSubscriber implements EventSubscriberInterface
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
        return array(
            FormEvents::PRE_SET_DATA => 'preInitializeMembership',
            FormEvents::SUBMIT       => 'postInitializeMembership',
        );
    }

    public function preInitializeMembership(FormEvent $event)
    {
        $beneficiary = $event->getData();
        if ($beneficiary && $beneficiary->getUser()) {
            $event->getForm()->get('email')->setData($beneficiary->getUser()->getEmail());
        }
    }

    public function postInitializeMembership(FormEvent $event)
    {
        $beneficiary = $event->getData();
        if ($beneficiary) {
            if (!$beneficiary->getUser()) {
                $beneficiary->setUser(new User());

                $username = $this->generateUsername($beneficiary);
                $beneficiary->getUser()->setUsername($username);

                $password = User::randomPassword();
                $beneficiary->getUser()->setPassword($password);
            }
            $beneficiary->getUser()->setEmail($event->getForm()->get('email')->getData());
        }
    }

    private function generateUsername(Beneficiary $beneficiary)
    {
        if (!$beneficiary->getFirstname() || !$beneficiary->getLastname()) {
            return null;
        }
        $username = User::makeUsername($beneficiary->getFirstname(), $beneficiary->getLastname());
        $qb = $this->em->createQueryBuilder();
        $usernames = $qb->select('u')->from('AppBundle\Entity\User', 'u')
            ->where($qb->expr()->like('u.username', $qb->expr()->literal($username . '%')))
            ->getQuery()
            ->getResult();
        $alreadyRegistered = (isset($usernames[$username])) ? $usernames[$username] : 0;
        if (count($usernames) || $alreadyRegistered) {
            $username = User::makeUsername($beneficiary->getFirstname(), $beneficiary->getLastname(), $alreadyRegistered + 1);
        }
        return $username;
    }
}