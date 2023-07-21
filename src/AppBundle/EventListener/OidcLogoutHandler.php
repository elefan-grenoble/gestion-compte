<?php

namespace AppBundle\EventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Logout\LogoutSuccessHandlerInterface;

class OidcLogoutHandler implements LogoutSuccessHandlerInterface
{
    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function onLogoutSuccess(Request $request) : RedirectResponse
    {
        return new RedirectResponse('/oauth/logout');
    }
}