<?php

namespace AppBundle\Controller;


use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Client\Provider\KeycloakClient;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;

class OAuthController extends Controller
{
    /**
    * @Route("/oauth/login", name="oauth_login")
    */
    public function index(ClientRegistry $clientRegistry): RedirectResponse
    {
        /** @var KeycloakClient $client */
        $client = $clientRegistry->getClient('keycloak');
        return $client->redirect();
    }

    /**
     * @Route("/oauth/callback", name="oauth_check")
     */
    public function check()
    {

    }
}
