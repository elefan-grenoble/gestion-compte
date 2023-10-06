<?php

namespace AppBundle\Security\Oauth2KeycloakProvider;


use Stevenmaguire\OAuth2\Client\Provider\Keycloak;

class MyKeycloak extends Keycloak
{

    public function __construct(array $options = [], array $collaborators = [])
    {
        $this->authServerUrl = $options['auth_server_url'];
        $this->realm = $options['realm'];
        parent::__construct($options, $collaborators);
    }


    /**
     * Get the default scopes used by this provider.
     *
     * This should not be a complete list of all scopes, but the minimum
     * required for the provider user interface!
     *
     * @return string[]
     */
    protected function getDefaultScopes()
    {
        $scopes = parent::getDefaultScopes();
        $scopes[] = 'address';
        return $scopes;
    }


}
