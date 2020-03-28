<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Membership;
use AppBundle\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LifecycleEventArgs;
use FOS\UserBundle\Event\FormEvent;
use FOS\UserBundle\Event\UserEvent;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class SetFirstPasswordListener{

    const  ROLE_PASSWORD_TO_SET  = 'ROLE_PASSWORD_TO_SET';

    /**
     * @var EntityManagerInterface
     */
    private $em;
    /**
     * @var TokenStorageInterface
     */
    private $token_storage;
    /**
     * @var UrlGeneratorInterface
     */
    private $router;


    public function __construct(EntityManagerInterface $entity_manager, TokenStorageInterface $token_storage, UrlGeneratorInterface $router)
    {
        $this->em = $entity_manager;
        $this->token_storage = $token_storage;
        $this->router = $router;
    }

    function prePersist(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();

        // only for users created trow "Beneficiary" entity
        if (!$entity instanceof Beneficiary) {
            return;
        }
        $user = $entity->getUser();

        if (!$user->getId()){
            $user->addRole(self::ROLE_PASSWORD_TO_SET);
        }
    }

    function onPasswordChanged(UserEvent $event)
    {
        $user = $event->getUser();
        $user->removeRole(self::ROLE_PASSWORD_TO_SET);
        $this->em->persist($user);
        $this->em->flush();
    }

    function forcePasswordChange(GetResponseEvent $event){

        $token = $this->token_storage->getToken();
        if ($token){
            $currentUser = $token->getUser();
            if($currentUser instanceof User){
                if($currentUser->hasRole(self::ROLE_PASSWORD_TO_SET)){
                    $route = $event->getRequest()->get('_route');
                    if ($route && $route != 'user_change_password'){
                        $changePassword = $this->router->generate('user_change_password');
                        $event->setResponse(new RedirectResponse($changePassword));
                    }
                }
            }
        }

    }

}