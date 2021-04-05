<?php

namespace App\EventListener;

use App\Entity\Beneficiary;
use App\Entity\User;
use App\Event\BeneficiaryCreatedEvent;
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
            FormEvents::SUBMIT       => 'postInitializeMembership',
        );
    }

    public function onBeforePersist(BeneficiaryCreatedEvent $event)
    {
        $this->makeUser($event->getBeneficiary());
    }

    public function postInitializeMembership(FormEvent $event)
    {
        $this->makeUser($event->getData());
    }

    private function makeUser(Beneficiary $beneficiary){
        if ($beneficiary) {
            if (!$beneficiary->getUser()) {
                $user = new User();
                $beneficiary->setUser($user);
            }

            if (!$beneficiary->getUser()->getUsername()) {

                $username = $this->generateUsername($beneficiary);
                $beneficiary->getUser()->setUsername($username);
            }

            if (!$beneficiary->getUser()->getPassword()) {
                $password = User::randomPassword();
                $beneficiary->getUser()->setPassword($password);
            }
        }
    }

    private function generateUsername(Beneficiary $beneficiary)
    {
        if (!$beneficiary->getFirstname() || !$beneficiary->getLastname()) {
            return null;
        }
        $username = User::makeUsername($beneficiary->getFirstname(), $beneficiary->getLastname());
        $qb = $this->em->createQueryBuilder();
        $usernames = $qb->select('u')->from('App\Entity\User', 'u')
            ->where($qb->expr()->like('u.username', $qb->expr()->literal($username . '%')))
            ->orderBy('u.username', 'DESC')
            ->getQuery()
            ->getResult();

        if (count($usernames)) {
            $username = $username . + count($usernames);
        }
        return $username;
    }
}
