<?php

namespace AppBundle\Security;

use AppBundle\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Client\Provider\KeycloakClient;
use KnpU\OAuth2ClientBundle\Security\Authenticator\SocialAuthenticator;
use Stevenmaguire\OAuth2\Client\Provider\KeycloakResourceOwner;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class KeycloakAuthenticator
 */
class KeycloakAuthenticator extends SocialAuthenticator
{

    /**
     * @var ClientRegistry
     */
    private $clientRegistry;
    /**
     * @var EntityManagerInterface
     */
    private $em;
    /**
     * @var RouterInterface
     */
    private $router;

    public function __construct(ClientRegistry $clientRegistry, EntityManagerInterface $entityManager, RouterInterface $router)
    {
        $this->clientRegistry = $clientRegistry;
        $this->em = $entityManager;
        $this->router = $router;
    }

    public function start(Request $request, \Symfony\Component\Security\Core\Exception\AuthenticationException $authException = null)
    {
        return new RedirectResponse(
            '/oauth/login', // might be the site, where users choose their oauth provider
            Response::HTTP_TEMPORARY_REDIRECT
        );
    }

    public function supports(Request $request)
    {
        return $request->attributes->get('_route') === 'oauth_check';
    }

    public function getCredentials(Request $request)
    {
        return $this->fetchAccessToken($this->getKeycloakClient());
    }

    public function getUser($credentials, \Symfony\Component\Security\Core\User\UserProviderInterface $userProvider)
    {
        $keycloakUser = $this->getKeycloakClient()->fetchUserFromToken($credentials);
        //existing user ?
        $existingUser = $this
            ->em
            ->getRepository(User::class)
            ->findOneBy(['openid' => $keycloakUser->getId()]);
        if ($existingUser) {
            $this->updateUser($keycloakUser,$existingUser);
            $this->em->persist($existingUser);
            $this->em->flush();
            return $existingUser;
        }
        // if user exist but never connected with keycloak
        $email = $keycloakUser->getEmail();
        /** @var User $userInDatabase */
        $userInDatabase = $this->em->getRepository(User::class)
            ->findOneBy(['email' => $email]);
        if($userInDatabase) {
            $userInDatabase->setOpenId($keycloakUser->getId());
            $this->updateUser($keycloakUser,$userInDatabase);
            $this->em->persist($userInDatabase);
            $this->em->flush();
            return $userInDatabase;
        }
        //user not exist in database
        $user = new User();
        $user->setOpenId($keycloakUser->getId());
        $this->updateUser($keycloakUser,$user);
        $this->em->persist($user);
        $this->em->flush();
        return $user;
    }

    /**
     * @param KeycloakResourceOwner $keycloakUser
     * @param User $userInDatabase
     * @return void
     */
    private function updateUser(KeycloakResourceOwner $keycloakUser,User $userInDatabase) : User
    {
        $userInDatabase->setOpenId($keycloakUser->getId());
        //todo : update all data
        return $userInDatabase;
    }

    public function onAuthenticationFailure(Request $request, \Symfony\Component\Security\Core\Exception\AuthenticationException $exception)
    {
        $message = strtr($exception->getMessageKey(), $exception->getMessageData());

        return new Response($message, Response::HTTP_FORBIDDEN);
    }

    public function onAuthenticationSuccess(Request $request, \Symfony\Component\Security\Core\Authentication\Token\TokenInterface $token, $providerKey)
    {
        // change "app_homepage" to some route in your app
        $targetUrl = $this->router->generate('dashboard');

        return new RedirectResponse($targetUrl);
    }

    /**
     * @return \KnpU\OAuth2ClientBundle\Client\Provider\KeycloakClient
     */
    private function getKeycloakClient() : KeycloakClient
    {
        return $this->clientRegistry->getClient('keycloak');
    }
}