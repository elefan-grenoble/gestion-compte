<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\Membership;
use AppBundle\Entity\PeriodPosition;
use AppBundle\Event\PeriodPositionFreedEvent;
use Doctrine\ORM\EntityManager;
use FOS\UserBundle\Event\GetResponseUserEvent;
use Monolog\Logger;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class OidcFirewallListener
{
    protected $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @param PeriodPositionFreedEvent $event
     * @throws \Doctrine\ORM\ORMException
     */
    public function onKernelRequest(GetResponseEvent $event)
    {
        $oidc_enable = $this->container->getParameter('oidc_enable');
        if ($oidc_enable){
            /** @var Request $request */
            $request = $event->getRequest();
            $uri = $request->getRequestUri();
            if ($uri === "/login"){ //redirect to oauth login
                $event->setResponse(new RedirectResponse("/oauth/login"));
            }
            foreach ( // access denied
            [
                '/admin/importcsv',
                '/profile/edit',
                '/resetting/request',
                '/member/office_tools',
                '/member/new',
                '/member/edit',
                '/member/join',
                '/ambassador/noregistration',
                '/ambassador/lateregistration',
                '/user/quick_new',
                '/user/pre_users',
                '/registrations',
                '/helloasso',
                '/services',
                '/admin/clients'
            ] as $path
            ){
                if (str_starts_with($uri,$path)){
                    throw new AccessDeniedException('Sorry, you cannot use this tool with openId connector enabled');
                }
            }
            foreach ( // access denied
            [
                'removeRole',
            ] as $path
            ){
                if (str_contains($uri,$path)){
                    throw new AccessDeniedException('Sorry, you cannot use this tool with openId connector enabled');
                }
            }
        }
    }
}
