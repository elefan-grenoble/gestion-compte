<?php

namespace App\EventListener;

use Doctrine\ORM\EntityManager;
use FOS\OAuthServerBundle\Event\OAuthEvent;

class OAuthEventListener
{
    protected $_em;

    public function __construct(EntityManager $entityManager)
    {
        $this->_em = $entityManager;
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
                $this->_em->persist($user);
                $this->_em->flush();
            }
        }
    }

    protected function getUser(OAuthEvent $event)
    {
        return $this->_em->getRepository('App:User')->findOneBy(array('username'=>$event->getUser()->getUsername()));
    }
}
