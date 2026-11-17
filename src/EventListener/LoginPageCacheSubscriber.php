<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Forbids the browser to keep the login page in its cache.
 *
 * The CSRF token of the login form lives in the session: a login page shown
 * again from the cache (restored tab on mobile, back button) carries the token
 * of a session that may no longer exist, and the login then fails with
 * "Invalid CSRF token". With no-store, the browser fetches a fresh page.
 */
class LoginPageCacheSubscriber implements EventSubscriberInterface
{
    public const LOGIN_ROUTE = 'fos_user_security_login';

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }
        if ($event->getRequest()->attributes->get('_route') !== self::LOGIN_ROUTE) {
            return;
        }

        $event->getResponse()->headers->addCacheControlDirective('no-store');
    }
}
