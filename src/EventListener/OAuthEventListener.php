<?php

namespace App\EventListener;

use Doctrine\ORM\EntityManager;
use FOS\OAuthServerBundle\Event\OAuthEvent;
use App\Entity\User;

class OAuthEventListener
{
    protected $em;

    public function __construct(EntityManager $entityManager)
    {
        $this->em = $entityManager;
    }

    public function onPreAuthorizationProcess(OAuthEvent $event)
    {
        if ($user = $this->getUser($event)) {
            $event->setAuthorizedClient(
                $user->isAuthorizedClient($event->getClient())
            );
        }
    }

    public function onPostAuthorizationProcess(OAuthEvent $event)
    {
        if ($event->isAuthorizedClient()) {
            if (null !== $client = $event->getClient()) {
                $user = $this->getUser($event);
                $user->addClient($client);
                $this->em->persist($user);
                $this->em->flush();
            }
        }
    }

    protected function getUser(OAuthEvent $event)
    {
        return $this->em->getRepository(User::class)->findOneBy(array('username'=>$event->getUser()->getUsername()));
    }
}
