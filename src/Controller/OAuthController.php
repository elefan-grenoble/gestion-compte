<?php

namespace App\Controller;


use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Client\Provider\KeycloakClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class OAuthController extends AbstractController
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
        $oidc_enable = $this->getParameter('oidc_enable');
        if ($oidc_enable) {
            /** @var KeycloakClient $client */
            $client = $clientRegistry->getClient('keycloak');
            $url = $this->generateUrl('homepage', [], UrlGeneratorInterface::ABSOLUTE_URL);
            $logout_url = $client->getOAuth2Provider()->getLogoutUrl(['redirect_uri' => $url]);
            return $this->redirect($logout_url);
        }else{
            $url = $this->generateUrl('homepage');
            return $this->redirect($url);
        }
    }

    /**
     * @Route("/oauth/callback", name="oauth_check")
     */
    public function check()
    {

    }
}
