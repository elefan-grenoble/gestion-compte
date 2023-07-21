<?php

namespace AppBundle\Controller;


use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Client\Provider\KeycloakClient;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class OAuthController extends Controller
{
    /**
    * @Route("/oauth/login", name="oauth_login")
    */
    public function login(ClientRegistry $clientRegistry): RedirectResponse
    {
        /** @var KeycloakClient $client */
        $client = $clientRegistry->getClient('keycloak');
        return $client->redirect();
    }

    /**
     * @Route("/oauth/logout", name="oauth_logout")
     */
    public function logout(ClientRegistry $clientRegistry): RedirectResponse
    {
        /** @var KeycloakClient $client */
        $client = $clientRegistry->getClient('keycloak');
        $url = $this->generateUrl('homepage',[],UrlGeneratorInterface::ABSOLUTE_URL);
        $logout_url = $client->getOAuth2Provider()->getLogoutUrl(['redirect_uri'=>$url]);
        return $this->redirect($logout_url);
    }

    /**
     * @Route("/oauth/callback", name="oauth_check")
     */
    public function check()
    {

    }
}
